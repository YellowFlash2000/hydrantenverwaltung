<?php
declare(strict_types=1);

/** Touren verwalten (ab Rolle Bearbeiter). Touren sind feste Pläne und bleiben über Prüfdurchgänge hinweg bestehen. */
final class TourController extends Controller
{
    public function index(): void
    {
        $campaign = Campaign::active();
        $tours = Tour::all();
        $progress = $campaign === null ? [] : CampaignTour::progress((int) $campaign['id']);
        $inCampaign = $campaign === null ? [] : array_flip(CampaignTour::tourIds((int) $campaign['id']));
        $unassigned = (int) Db::val('SELECT COUNT(*) FROM {hydrants} WHERE aktiv = 1 AND tour_id IS NULL');
        $this->render('tours/index', [
            'title' => 'Touren', 'campaign' => $campaign, 'tours' => $tours, 'progress' => $progress, 'inCampaign' => $inCampaign,
            'counts' => Tour::hydrantCounts(), 'unassigned' => $unassigned,
        ]);
    }

    public function createForm(): void
    {
        $this->render('tours/form', ['title' => 'Tour anlegen', 'tour' => null, 'form' => ['name' => '', 'beschreibung' => ''], 'errors' => []]);
    }

    public function create(): void
    {
        [$form, $errors] = $this->readForm(null);
        if ($errors) {
            $this->render('tours/form', ['title' => 'Tour anlegen', 'tour' => null, 'form' => $form, 'errors' => $errors], 422);
            return;
        }
        $id = Tour::insert($form['name'], $form['beschreibung'] === '' ? null : $form['beschreibung']);
        Audit::log('tour_angelegt', 'tour', $id, $form['name']);
        flash('success', 'Die Tour „' . $form['name'] . '“ wurde angelegt. Ordnen Sie ihr jetzt in der Hydrantenliste Hydranten zu.');
        redirect('touren/' . $id);
    }

    public function show(array $params): void
    {
        $t = $this->load($params);
        if ($t === null) {
            return;
        }
        $campaign = Campaign::active();
        $ct = $campaign === null ? null : CampaignTour::forTour((int) $campaign['id'], (int) $t['id']);
        $hydrants = Tour::hydrants((int) $t['id'], $ct !== null ? (int) $campaign['id'] : 0);
        $done = $ct === null ? 0 : count(array_filter($hydrants, static fn ($h) => $h['rev_id'] !== null));
        $defects = $ct === null ? 0 : count(array_filter($hydrants, static fn ($h) => $h['rev_ergebnis'] === Revision::DEFECT));
        $outings = Db::all('SELECT teilnehmer, started_at FROM {tour_outings} WHERE tour_id = ? ORDER BY id DESC LIMIT 20', [(int) $t['id']]);
        $this->render('tours/show', [
            'title' => (string) $t['name'], 't' => $t, 'campaign' => $campaign, 'ct' => $ct, 'hydrants' => $hydrants,
            'done' => $done, 'defects' => $defects, 'outings' => $outings, 'link' => $ct === null ? '' : CampaignTour::link($ct),
            'works' => $ct !== null && CampaignTour::linkWorks($ct),
            'revCount' => Tour::revisionCount((int) $t['id']),
            'scripts' => ['js/tours.js'],
        ]);
    }

    public function editForm(array $params): void
    {
        $t = $this->load($params);
        if ($t === null) {
            return;
        }
        $this->render('tours/form', ['title' => 'Tour bearbeiten', 'tour' => $t, 'form' => ['name' => (string) $t['name'], 'beschreibung' => (string) ($t['beschreibung'] ?? '')], 'errors' => []]);
    }

    public function update(array $params): void
    {
        $t = $this->load($params);
        if ($t === null) {
            return;
        }
        [$form, $errors] = $this->readForm((int) $t['id']);
        if ($errors) {
            $this->render('tours/form', ['title' => 'Tour bearbeiten', 'tour' => $t, 'form' => $form, 'errors' => $errors], 422);
            return;
        }
        Db::exec('UPDATE {tours} SET name = ?, beschreibung = ? WHERE id = ?', [$form['name'], $form['beschreibung'] === '' ? null : $form['beschreibung'], (int) $t['id']]);
        Audit::log('tour_geaendert', 'tour', (int) $t['id'], (string) $form['name']);
        flash('success', 'Die Tour wurde gespeichert.');
        redirect('touren/' . (int) $t['id']);
    }

    public function delete(array $params): void
    {
        $t = $this->load($params);
        if ($t === null) {
            return;
        }
        if (Tour::revisionCount((int) $t['id']) > 0) {
            flash('error', 'Zu dieser Tour gibt es bereits Revisionen. Sie kann nicht gelöscht werden.');
            redirect('touren/' . (int) $t['id']);
        }
        Db::exec('UPDATE {hydrants} SET tour_id = NULL WHERE tour_id = ?', [(int) $t['id']]);
        Db::exec('DELETE FROM {tours} WHERE id = ?', [(int) $t['id']]);
        Audit::log('tour_geloescht', 'tour', (int) $t['id'], (string) $t['name']);
        flash('success', 'Die Tour „' . $t['name'] . '“ wurde gelöscht. Ihre Hydranten haben jetzt keine Tour.');
        redirect('touren');
    }

    /** Hydranten mehreren auf einmal einer Tour zuweisen oder aus der Tour nehmen. */
    public function assign(): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', is_array($_POST['ids'] ?? null) ? $_POST['ids'] : []), static fn ($i) => $i > 0)));
        $back = $this->backUrl();
        if (!$ids) {
            flash('error', 'Bitte zuerst Hydranten in der Liste ankreuzen.');
            redirect($back[0], $back[1]);
        }
        $remove = $this->post('aktion') === 'entfernen';
        $tourId = $remove ? null : (int) $this->post('tour_id');
        if (!$remove) {
            $t = $tourId ? Tour::find($tourId) : null;
            if ($t === null) {
                flash('error', 'Bitte eine Tour wählen.');
                redirect($back[0], $back[1]);
            }
        }
        $n = 0;
        foreach (array_slice($ids, 0, 2000) as $id) {
            $n += Db::exec('UPDATE {hydrants} SET tour_id = ?, updated_at = ? WHERE id = ?', [$tourId, now(), $id]);
        }
        Audit::log($remove ? 'hydranten_aus_tour_entfernt' : 'hydranten_tour_zugewiesen', 'tour', $tourId, $n . ' Hydrant(en)' . (!$remove && isset($t) ? ' → ' . $t['name'] : ''));
        flash('success', $remove ? $n . ' Hydrant(en) aus der Tour genommen.' : $n . ' Hydrant(en) der Tour „' . ($t['name'] ?? '') . '“ zugewiesen.');
        redirect($back[0], $back[1]);
    }

    // ---- Hilfen ---------------------------------------------------------------

    /** @return array{0: string, 1: array<string,string>} Ziel nach dem Zuweisen */
    private function backUrl(): array
    {
        $tour = (int) $this->post('back_tour');
        if ($tour > 0) {
            return ['touren/' . $tour, []];
        }
        parse_str($this->post('back_query'), $q);
        $out = [];
        foreach (['q', 'typ', 'lage', 'status', 'tour'] as $k) {
            if (isset($q[$k]) && is_string($q[$k]) && $q[$k] !== '') {
                $out[$k] = $q[$k];
            }
        }
        return ['hydranten', $out];
    }

    private function load(array $params): ?array
    {
        $t = Tour::find((int) ($params['id'] ?? 0));
        if ($t === null) {
            $this->fail(404, 'Nicht gefunden', 'Diese Tour gibt es nicht.');
        }
        return $t;
    }

    /** @return array{0: array<string,string>, 1: string[]} */
    private function readForm(?int $selfId): array
    {
        $form = ['name' => $this->post('name'), 'beschreibung' => $this->post('beschreibung')];
        $errors = [];
        if ($form['name'] === '' || mb_strlen($form['name']) > 120) {
            $errors[] = 'Bitte einen Namen angeben (höchstens 120 Zeichen).';
        } elseif (Tour::nameTaken($form['name'], $selfId)) {
            $errors[] = 'Eine Tour mit diesem Namen gibt es schon.';
        }
        if (mb_strlen($form['beschreibung']) > 2000) {
            $errors[] = 'Die Beschreibung ist zu lang (höchstens 2000 Zeichen).';
        }
        return [$form, $errors];
    }
}
