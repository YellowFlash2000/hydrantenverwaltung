<?php
declare(strict_types=1);

/** Revision über den Tour-Link ohne Anmeldung. Der geheime Link ist der Zugang. */
final class TourLinkController extends Controller
{
    private const THROTTLE_KIND = 'tourlink';

    /** Prüft den Link. Gibt die Tour zurück oder zeigt eine neutrale Fehlerseite. */
    private function tour(array $params): ?array
    {
        $ip = client_ip();
        if (Throttle::tooMany(self::THROTTLE_KIND, '', $ip, 1000000, 40, 600)) {
            $this->invalid(429);
            return null;
        }
        $tour = CampaignTour::byToken((string) ($params['token'] ?? ''));
        if ($tour === null) {
            Throttle::hit(self::THROTTLE_KIND, '', $ip);
            Throttle::purge();
            $this->invalid(404);
            return null;
        }
        if (!CampaignTour::linkWorks($tour)) {
            $this->invalid(404);
            return null;
        }
        return $tour;
    }

    private function invalid(int $status): void
    {
        $this->render('tourlink/invalid', ['title' => 'Link nicht gültig'], $status);
    }

    private function self(array $tour, string $suffix = ''): string
    {
        return 't/' . $tour['token'] . $suffix;
    }

    // ---- Übersicht der Tour -----------------------------------------------------

    public function show(array $params): void
    {
        $tour = $this->tour($params);
        if ($tour === null) {
            return;
        }
        $outing = TourAccess::outing($tour);
        if ($outing === null || isset($_GET['teilnehmer'])) {
            $this->render('tourlink/names', [
                'title' => $tour['name'], 'tour' => $tour, 'names' => (string) ($outing['teilnehmer'] ?? ''), 'errors' => [],
            ]);
            return;
        }
        $campaignId = (int) $tour['campaign_id'];
        $hydrants = Tour::hydrants((int) $tour['id'], $campaignId);
        $points = [];
        foreach ($hydrants as $h) {
            if ($h['lat'] !== null && $h['lon'] !== null) {
                $points[] = TourAccess::point($tour, $h);
            }
        }
        $covers = [];
        foreach (Db::all("SELECT hydrant_id, id FROM {hydrant_images} WHERE status = 'ok' ORDER BY sortierung, id") as $c) {
            $covers[(int) $c['hydrant_id']] ??= (int) $c['id'];
        }
        $done = count(array_filter($hydrants, static fn ($h) => $h['rev_id'] !== null));
        $onlyOpen = ($_GET['nur'] ?? '') === 'offen';
        if ($onlyOpen) {
            $hydrants = array_values(array_filter($hydrants, static fn ($h) => $h['rev_id'] === null));
            $points = [];
            foreach ($hydrants as $h) {
                if ($h['lat'] !== null && $h['lon'] !== null) {
                    $points[] = TourAccess::point($tour, $h);
                }
            }
        }
        $this->render('tourlink/show', [
            'title' => (string) $tour['name'], 'tour' => $tour, 'outing' => $outing, 'hydrants' => $hydrants, 'points' => $points,
            'covers' => $covers, 'done' => $done, 'total' => $done + ($onlyOpen ? count($hydrants) : count($hydrants) - $done), 'onlyOpen' => $onlyOpen,
            'mapCenter' => Settings::mapDefault(),
            'styles' => ['vendor/leaflet/leaflet.css'], 'scripts' => ['vendor/leaflet/leaflet.js', 'js/map.js', 'js/tourfilter.js'], 'wide' => true,
        ]);
    }

    public function names(array $params): void
    {
        $tour = $this->tour($params);
        if ($tour === null) {
            return;
        }
        [$names, $err] = TourAccess::cleanNames($this->post('teilnehmer'));
        if ($err !== null) {
            $this->render('tourlink/names', ['title' => $tour['name'], 'tour' => $tour, 'names' => $names, 'errors' => [$err]], 422);
            return;
        }
        TourAccess::start($tour, $names);
        redirect($this->self($tour));
    }

    // ---- Einzelner Hydrant --------------------------------------------------------

    /** @return array{0:array,1:array}|null [Tour, Hydrant] */
    private function hydrant(array $params): ?array
    {
        $tour = $this->tour($params);
        if ($tour === null) {
            return null;
        }
        $h = Hydrant::find((int) ($params['id'] ?? 0));
        if ($h === null || (int) $h['aktiv'] !== 1 || (int) ($h['tour_id'] ?? 0) !== (int) $tour['id']) {
            $this->fail(404, 'Nicht gefunden', 'Dieser Hydrant gehört nicht zu dieser Tour.');
            return null;
        }
        return [$tour, $h];
    }

    public function hydrantPage(array $params): void
    {
        $r = $this->hydrant($params);
        if ($r === null) {
            return;
        }
        [$tour, $h] = $r;
        if (TourAccess::outing($tour) === null) {
            redirect($this->self($tour));
        }
        $this->renderHydrant($tour, $h, ['ergebnis' => '', 'maengel' => [], 'bemerkung' => ''], []);
    }

    private function renderHydrant(array $tour, array $h, array $form, array $errors, int $status = 200): void
    {
        $campaignId = (int) $tour['campaign_id'];
        $current = Db::one(
            'SELECT r.id, r.ergebnis, r.revised_at, r.bemerkung, o.teilnehmer FROM {revisions} r LEFT JOIN {tour_outings} o ON o.id = r.outing_id'
            . ' WHERE r.hydrant_id = ? AND r.campaign_id = ? ORDER BY r.id DESC LIMIT 1',
            [(int) $h['id'], $campaignId]
        );
        $h['rev_ergebnis'] = $current['ergebnis'] ?? null;
        $others = [];
        foreach (Tour::hydrants((int) $tour['id'], $campaignId) as $o) {
            if ((int) $o['id'] !== (int) $h['id'] && $o['lat'] !== null && $o['lon'] !== null) {
                $others[] = TourAccess::point($tour, $o);
            }
        }
        $this->render('tourlink/hydrant', [
            'title' => (string) $h['kennung'], 'tour' => $tour, 'h' => $h, 'current' => $current,
            'images' => Hydrant::images((int) $h['id'], false, null, (int) (TourAccess::outing($tour)['id'] ?? 0) ?: null), 'others' => $others,
            'outing' => TourAccess::outing($tour),
            'defectList' => DefectType::forType((string) $h['typ']), 'revForm' => $form, 'revErrors' => $errors,
            'styles' => ['vendor/leaflet/leaflet.css'],
            'scripts' => ['vendor/leaflet/leaflet.js', 'js/map.js', 'js/gallery.js', 'js/marker.js', 'js/upload.js', 'js/revision.js'],
        ], $status);
    }

    public function save(array $params): void
    {
        $r = $this->hydrant($params);
        if ($r === null) {
            return;
        }
        [$tour, $h] = $r;
        $outing = TourAccess::outing($tour);
        if ($outing === null) {
            flash('error', 'Bitte zuerst die Teilnehmer eintragen.');
            redirect($this->self($tour));
        }
        [$values, $errors] = Revision::readForm($_POST, $h);
        $photos = ['files' => [], 'errors' => []];
        if ($values['ergebnis'] === Revision::DEFECT) {
            $photos = Revision::collectPhotos('fotos');
            $errors = array_merge($errors, $photos['errors']);
        }
        $names = [];
        if (!$errors && $photos['files']) {
            [$names, $photoErrors] = Revision::storePhotos($photos['files']);
            $errors = array_merge($errors, $photoErrors);
        }
        if ($errors) {
            $form = ['ergebnis' => (string) ($_POST['ergebnis'] ?? ''), 'maengel' => $values['defects'], 'bemerkung' => (string) ($_POST['bemerkung'] ?? '')];
            $this->renderHydrant($tour, $h, $form, $errors, 422);
            return;
        }
        $campaign = Campaign::find((int) $tour['campaign_id']);
        $id = Revision::create($h, $values, 'tour', null, (int) $outing['id'], $tour, $campaign, $names);
        Audit::log('revision_erfasst', 'hydrant', (int) $h['id'], $h['kennung'] . ': ' . ($values['ergebnis'] === Revision::DEFECT ? 'Mangel' : 'in Ordnung') . ' (#' . $id . ', Tour-Link, ' . $outing['teilnehmer'] . ')');
        flash('success', '„' . $h['kennung'] . '“ ist gespeichert.');
        redirect($this->self($tour) . '#h' . (int) $h['id']);
    }

    public function image(array $params): void
    {
        $tour = $this->tour($params);
        if ($tour === null) {
            return;
        }
        $row = Db::one(
            'SELECT i.datei, i.status, i.outing_id FROM {hydrant_images} i JOIN {hydrants} h ON h.id = i.hydrant_id WHERE i.id = ? AND h.tour_id = ? AND h.aktiv = 1',
            [(int) ($params['id'] ?? 0), (int) $tour['id']]
        );
        if ($row !== null && !HydrantImages::mayView($row, false, null, (int) (TourAccess::outing($tour)['id'] ?? 0) ?: null)) {
            $row = null;
        }
        if ($row === null || !Images::serve((string) $row['datei'], (string) ($params['size'] ?? ''))) {
            $this->fail(404, 'Nicht gefunden', 'Dieses Bild gibt es nicht.');
        }
    }

    // ---- Bilder des Hydranten: Vorschläge und Veraltet-Meldungen -------------------

    /** Neue Bilder einreichen; sie erscheinen nach der Freigabe durch einen Bearbeiter. */
    public function suggestImages(array $params): void
    {
        $r = $this->hydrant($params);
        if ($r === null) {
            return;
        }
        [$tour, $h] = $r;
        $outing = TourAccess::outing($tour);
        if ($outing === null) {
            flash('error', 'Bitte zuerst die Teilnehmer eintragen.');
            redirect($this->self($tour));
        }
        $back = $this->self($tour) . '/h/' . (int) $h['id'] . '#bilder';
        $uploads = HydrantImages::collectUploads(HydrantImages::MAX_PER_SUGGESTION);
        if ($uploads['errors']) {
            foreach ($uploads['errors'] as $e) {
                flash('error', $e);
            }
        } elseif (!$uploads['files']) {
            flash('error', 'Bitte mindestens ein Bild auswählen.');
        } else {
            $replace = HydrantImages::replaceTarget((int) $h['id'], $_POST['ersetzt_id'] ?? null);
            [$saved, $msgs] = HydrantImages::store((int) $h['id'], $uploads['files'], [
                'status' => HydrantImages::PROPOSAL, 'quelle' => 'tour', 'outing_id' => (int) $outing['id'],
                'von' => (string) $outing['teilnehmer'], 'ersetzt_id' => $replace,
            ]);
            foreach ($msgs as $m) {
                flash($m[0], $m[1]);
            }
            if ($saved > 0) {
                Audit::log('bild_vorgeschlagen', 'hydrant', (int) $h['id'], $h['kennung'] . ': ' . $saved . ' Bild(er)' . ($replace !== null ? ', als Ersatz für Bild #' . $replace : '') . ' (Tour-Link, ' . $outing['teilnehmer'] . ')');
                flash('success', ($saved === 1 ? 'Das Bild wurde' : $saved . ' Bilder wurden') . ' eingereicht. Es erscheint für alle, sobald ein Bearbeiter es freigibt.');
            }
        }
        redirect($back);
    }

    /** Ein freigegebenes Bild als veraltet melden. */
    public function reportOutdated(array $params): void
    {
        $r = $this->hydrant($params);
        if ($r === null) {
            return;
        }
        [$tour, $h] = $r;
        $outing = TourAccess::outing($tour);
        if ($outing === null) {
            flash('error', 'Bitte zuerst die Teilnehmer eintragen.');
            redirect($this->self($tour));
        }
        $img = Db::one('SELECT id, status, veraltet_at FROM {hydrant_images} WHERE id = ? AND hydrant_id = ?', [(int) ($params['iid'] ?? 0), (int) $h['id']]);
        if ($img === null) {
            $this->fail(404, 'Nicht gefunden', 'Dieses Bild gibt es nicht.');
            return;
        }
        if (HydrantImages::reportOutdated($img, (string) $outing['teilnehmer'])) {
            Audit::log('bild_veraltet_gemeldet', 'hydrant', (int) $h['id'], $h['kennung'] . ': Bild #' . (int) $img['id'] . ' (Tour-Link, ' . $outing['teilnehmer'] . ')');
            flash('success', 'Danke, das Bild ist als veraltet gemeldet. Ein Bearbeiter entscheidet über das Entfernen.');
        } else {
            flash('error', 'Dieses Bild kann nicht als veraltet gemeldet werden.');
        }
        redirect($this->self($tour) . '/h/' . (int) $h['id'] . '#bilder');
    }
}
