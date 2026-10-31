<?php
declare(strict_types=1);

/** Prüfdurchgänge verwalten (ab Rolle Bearbeiter). */
final class CampaignController extends Controller
{
    public function index(): void
    {
        $campaigns = Campaign::all();
        $stats = [];
        foreach ($campaigns as $c) {
            $stats[(int) $c['id']] = Campaign::stats((int) $c['id']);
        }
        $this->render('campaigns/index', ['title' => 'Prüfdurchgänge', 'campaigns' => $campaigns, 'stats' => $stats, 'active' => Campaign::active()]);
    }

    public function createForm(): void
    {
        $prev = Campaign::previous();
        $all = array_map(static fn ($x) => (int) $x['id'], Tour::all());
        $selected = $prev === null ? $all : array_values(array_intersect($all, CampaignTour::tourIds((int) $prev['id'])));
        $this->renderForm(['name' => 'Prüfung ' . date('Y'), 'start_date' => date('Y-m-d'), 'touren' => $selected], []);
    }

    public function create(): void
    {
        $tourIds = $this->postedTours();
        $form = ['name' => $this->post('name'), 'start_date' => $this->post('start_date'), 'touren' => $tourIds];
        $errors = [];
        if ($form['name'] === '' || mb_strlen($form['name']) > 120) {
            $errors[] = 'Bitte einen Namen angeben (höchstens 120 Zeichen).';
        }
        $d = \DateTime::createFromFormat('!Y-m-d', $form['start_date']);
        if ($d === false || $d->format('Y-m-d') !== $form['start_date']) {
            $errors[] = 'Bitte ein gültiges Startdatum angeben.';
        }
        if ($errors) {
            $this->renderForm($form, $errors, 422);
            return;
        }
        $id = Campaign::create($form['name'], $form['start_date'], $tourIds);
        Audit::log('pruefdurchgang_angelegt', 'campaign', $id, $form['name'] . ' (' . count($tourIds) . ' Touren)');
        flash('success', 'Der Prüfdurchgang „' . $form['name'] . '“ ist angelegt und aktiv. Die Links der Touren finden Sie hier.');
        redirect('pruefungen/' . $id);
    }

    /** Verwaltung eines Prüfdurchgangs: Touren, Links, Fortschritt. */
    public function show(array $params): void
    {
        $c = $this->load($params);
        if ($c === null) {
            return;
        }
        $cid = (int) $c['id'];
        $this->render('campaigns/show', [
            'title' => (string) $c['name'], 'c' => $c, 'cts' => CampaignTour::forCampaign($cid), 'progress' => CampaignTour::progress($cid),
            'tours' => Tour::all(), 'counts' => Tour::hydrantCounts(), 'selected' => CampaignTour::tourIds($cid),
            'stats' => Campaign::stats($cid), 'scripts' => ['js/tours.js'],
        ]);
    }

    /** Touren des Durchgangs festlegen: neue hinzufügen, abgewählte entfernen (solange es dafür keine Revisionen gibt). */
    public function setTours(array $params): void
    {
        $c = $this->load($params);
        if ($c === null) {
            return;
        }
        $cid = (int) $c['id'];
        if ((int) $c['aktiv'] !== 1) {
            flash('error', 'Die Touren eines beendeten Prüfdurchgangs lassen sich nicht mehr ändern.');
            redirect('pruefungen/' . $cid);
        }
        $want = $this->postedTours();
        $have = CampaignTour::tourIds($cid);
        $added = 0;
        foreach (array_diff($want, $have) as $tid) {
            CampaignTour::add($cid, (int) $tid);
            $added++;
        }
        $removed = 0;
        $kept = [];
        foreach (array_diff($have, $want) as $tid) {
            if (CampaignTour::revisionCount($cid, (int) $tid) > 0) {
                $kept[] = (string) (Tour::find((int) $tid)['name'] ?? $tid);
                continue;
            }
            Db::exec('DELETE FROM {campaign_tours} WHERE campaign_id = ? AND tour_id = ?', [$cid, (int) $tid]);
            $removed++;
        }
        Audit::log('pruefdurchgang_touren_geaendert', 'campaign', $cid, '+' . $added . ' / -' . $removed);
        flash('success', 'Gespeichert: ' . $added . ' Tour(en) hinzugefügt, ' . $removed . ' entfernt.');
        if ($kept) {
            flash('error', 'Bleiben im Durchgang, weil es dafür schon Revisionen gibt: ' . implode(', ', $kept));
        }
        redirect('pruefungen/' . $cid);
    }

    /** Link einer Tour sperren, freigeben oder durch einen neuen ersetzen. */
    public function link(array $params): void
    {
        $ct = $this->loadCt($params);
        if ($ct === null) {
            return;
        }
        $action = $this->post('aktion');
        if ($action === 'sperren') {
            Db::exec('UPDATE {campaign_tours} SET link_aktiv = 0 WHERE id = ?', [(int) $ct['ct_id']]);
            Audit::log('tourlink_gesperrt', 'tour', (int) $ct['id'], (string) $ct['name']);
            flash('success', 'Der Link ist gesperrt. Wer ihn öffnet, sieht eine Hinweisseite.');
        } elseif ($action === 'freigeben') {
            Db::exec('UPDATE {campaign_tours} SET link_aktiv = 1 WHERE id = ?', [(int) $ct['ct_id']]);
            Audit::log('tourlink_freigegeben', 'tour', (int) $ct['id'], (string) $ct['name']);
            flash('success', 'Der Link ist wieder freigegeben.');
        } elseif ($action === 'neu') {
            Db::exec('UPDATE {campaign_tours} SET token = ?, link_aktiv = 1 WHERE id = ?', [CampaignTour::newToken(), (int) $ct['ct_id']]);
            Audit::log('tourlink_erneuert', 'tour', (int) $ct['id'], (string) $ct['name']);
            flash('success', 'Es gibt einen neuen Link. Der bisherige Link ist ungültig.');
        }
        redirect('pruefungen/' . (int) $ct['campaign_id']);
    }

    /** Link per E-Mail an eingetragene Adressen senden. */
    public function mail(array $params): void
    {
        $ct = $this->loadCt($params);
        if ($ct === null) {
            return;
        }
        $back = 'pruefungen/' . (int) $ct['campaign_id'];
        if (!CampaignTour::linkWorks($ct)) {
            flash('error', 'Der Link ist gesperrt oder der Prüfdurchgang beendet. Bitte zuerst freigeben.');
            redirect($back);
        }
        $raw = preg_split('/[\s,;]+/', $this->post('adressen'), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $addresses = array_values(array_unique(array_map('strtolower', $raw)));
        $bad = array_filter($addresses, static fn ($a) => filter_var($a, FILTER_VALIDATE_EMAIL) === false);
        if (!$addresses || $bad || count($addresses) > 10) {
            flash('error', !$addresses ? 'Bitte mindestens eine E-Mail-Adresse eintragen.' : (count($addresses) > 10 ? 'Bitte höchstens 10 Adressen auf einmal.' : 'Ungültige Adresse: ' . implode(', ', $bad)));
            redirect($back);
        }
        $campaign = Campaign::find((int) $ct['campaign_id']);
        $app = Settings::get('app_name');
        $note = $this->post('text');
        $body = "Hallo,\n\n"
            . ($note !== '' ? mb_substr($note, 0, 1000) . "\n\n" : '')
            . "über den folgenden Link können Sie die Revision der Tour \"{$ct['name']}\" (" . ($campaign['name'] ?? '') . ") eintragen. Eine Anmeldung ist nicht nötig.\n\n"
            . CampaignTour::link($ct) . "\n\n"
            . "Bitte geben Sie den Link nicht weiter. Beim ersten Öffnen werden die Namen der Teilnehmer abgefragt.\n\n{$app}\n";
        $sent = 0;
        $failed = [];
        foreach ($addresses as $a) {
            $err = Mailer::send($a, 'Revisionstour ' . $ct['name'] . ' – ' . $app, $body);
            if ($err === null) {
                $sent++;
            } else {
                $failed[] = $a . ' (' . $err . ')';
            }
        }
        Audit::log('tourlink_versendet', 'tour', (int) $ct['id'], $sent . ' Mail(s)');
        if ($sent > 0) {
            flash('success', 'Der Link wurde an ' . $sent . ' Adresse(n) gesendet.');
        }
        foreach ($failed as $f) {
            flash('error', 'Nicht gesendet: ' . $f);
        }
        redirect($back);
    }

    public function end(array $params): void
    {
        $c = Campaign::find((int) ($params['id'] ?? 0));
        if ($c === null) {
            $this->fail(404, 'Nicht gefunden', 'Diesen Prüfdurchgang gibt es nicht.');
            return;
        }
        Campaign::end((int) $c['id']);
        Audit::log('pruefdurchgang_beendet', 'campaign', (int) $c['id'], (string) $c['name']);
        flash('success', 'Der Prüfdurchgang „' . $c['name'] . '“ wurde beendet. Die Tour-Links gelten nicht mehr.');
        redirect('pruefungen');
    }

    public function reopen(array $params): void
    {
        $c = Campaign::find((int) ($params['id'] ?? 0));
        if ($c === null) {
            $this->fail(404, 'Nicht gefunden', 'Diesen Prüfdurchgang gibt es nicht.');
            return;
        }
        if (!Campaign::reopen((int) $c['id'])) {
            flash('error', 'Es ist bereits ein anderer Prüfdurchgang aktiv. Bitte diesen zuerst beenden.');
        } else {
            Audit::log('pruefdurchgang_geoeffnet', 'campaign', (int) $c['id'], (string) $c['name']);
            flash('success', 'Der Prüfdurchgang „' . $c['name'] . '“ ist wieder aktiv.');
        }
        redirect('pruefungen');
    }

    private function load(array $params): ?array
    {
        $c = Campaign::find((int) ($params['id'] ?? 0));
        if ($c === null) {
            $this->fail(404, 'Nicht gefunden', 'Diesen Prüfdurchgang gibt es nicht.');
        }
        return $c;
    }

    private function loadCt(array $params): ?array
    {
        $ct = CampaignTour::find((int) ($params['ct'] ?? 0));
        if ($ct === null || (int) $ct['campaign_id'] !== (int) ($params['id'] ?? 0)) {
            $this->fail(404, 'Nicht gefunden', 'Diese Tour gibt es in diesem Prüfdurchgang nicht.');
            return null;
        }
        return $ct;
    }

    /** @return int[] gewählte, existierende Touren aus dem Formular */
    private function postedTours(): array
    {
        $ids = is_array($_POST['touren'] ?? null) ? array_map('intval', $_POST['touren']) : [];
        $valid = array_map(static fn ($x) => (int) $x['id'], Tour::all());
        return array_values(array_intersect($valid, $ids));
    }

    private function renderForm(array $form, array $errors, int $status = 200): void
    {
        $this->render('campaigns/form', [
            'title' => 'Prüfdurchgang anlegen', 'form' => $form, 'errors' => $errors,
            'active' => Campaign::active(), 'tours' => Tour::all(), 'counts' => Tour::hydrantCounts(), 'scripts' => ['js/tours.js'],
        ], $status);
    }
}
