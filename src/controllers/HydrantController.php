<?php
declare(strict_types=1);

final class HydrantController extends Controller
{
    // ---- Übersicht ------------------------------------------------------------

    public function index(): void
    {
        $tours = Tour::all();
        $f = HydrantFilter::parse($_GET, $tours);
        $campaign = Campaign::active();
        [$whereSql, $params] = HydrantFilter::where($f);

        $rows = Db::all(
            'SELECT h.id, h.kennung, h.typ, h.durchmesser_dn, h.durchfluss_lmin, h.durchfluss_zeichen, h.druck_bar, h.position_art, h.lat, h.lon, h.aktiv, h.tour_id,'
            . ' t.name AS tour_name, ct.id AS ct_id, r.ergebnis AS rev_ergebnis, ra.ergebnis_all, ra.camp_all,'
            . ' (SELECT i.id FROM {hydrant_images} i WHERE i.hydrant_id = h.id ORDER BY i.sortierung, i.id LIMIT 1) AS cover_id'
            . ' FROM {hydrants} h LEFT JOIN {tours} t ON t.id = h.tour_id'
            . ' LEFT JOIN {campaign_tours} ct ON ct.tour_id = h.tour_id AND ct.campaign_id = ?'
            . ' LEFT JOIN (SELECT r1.hydrant_id, r1.ergebnis FROM {revisions} r1'
            . '   JOIN (SELECT hydrant_id, MAX(id) AS mid FROM {revisions} WHERE campaign_id = ? GROUP BY hydrant_id) m ON m.mid = r1.id) r ON r.hydrant_id = h.id'
            . ' LEFT JOIN (SELECT r2.hydrant_id, r2.ergebnis AS ergebnis_all, r2.campaign_id AS camp_all FROM {revisions} r2'
            . '   JOIN (SELECT hydrant_id, MAX(id) AS mid FROM {revisions} GROUP BY hydrant_id) m2 ON m2.mid = r2.id) ra ON ra.hydrant_id = h.id'
            . $whereSql,
            array_merge([$campaign === null ? 0 : (int) $campaign['id'], $campaign === null ? 0 : (int) $campaign['id']], $params)
        );
        usort($rows, static fn (array $a, array $b): int => strnatcasecmp((string) $a['kennung'], (string) $b['kennung']));

        $points = [];
        foreach ($rows as $r) {
            if ($r['lat'] !== null && $r['lon'] !== null) {
                $points[] = Hydrant::mapPoint($r);
            }
        }

        $counts = Db::one('SELECT COUNT(*) AS n, SUM(CASE WHEN aktiv = 1 THEN 1 ELSE 0 END) AS active FROM {hydrants}') ?? ['n' => 0, 'active' => 0];

        $this->render('hydrants/list', [
            'title'   => 'Hydranten',
            'wide'    => true,
            'styles'  => ['vendor/leaflet/leaflet.css'],
            'scripts' => ['vendor/leaflet/leaflet.js', 'js/map.js', 'js/tours.js'],
            'rows'    => $rows,
            'usedTypes' => array_flip(array_map(static fn ($r) => (string) $r['typ'], $rows)),
            'points'  => $points,
            'mapCenter' => Settings::mapDefault(),
            'f'       => $f,
            'tours'   => $tours,
            'campaign' => $campaign,
            'hasManual' => (int) Db::val('SELECT COUNT(*) FROM {revisions} x WHERE x.campaign_id IS NULL AND x.ergebnis = ? AND x.id = (SELECT MAX(y.id) FROM {revisions} y WHERE y.hydrant_id = x.hydrant_id)', [Revision::DEFECT]) > 0,
            'total'   => (int) $counts['n'],
            'active'  => (int) $counts['active'],
        ]);
    }

    // ---- Anlegen --------------------------------------------------------------

    public function createForm(): void
    {
        $this->renderForm(null, ['kennung' => '', 'typ' => (string) (array_key_first(HydrantType::choices()) ?? ''), 'durchmesser_dn' => '', 'durchfluss_lmin' => '', 'durchfluss_zeichen' => '', 'druck_bar' => '',
            'position_art' => '', 'lat' => '', 'lon' => '', 'bemerkung' => ''], []);
    }

    /** Gewählte Tour aus dem Formular: null = keine; false = ungültig. @return int|null|false */
    private function tourFromPost(): int|null|false
    {
        $v = isset($_POST['tour_id']) && is_string($_POST['tour_id']) ? trim($_POST['tour_id']) : '';
        if ($v === '') {
            return null;
        }
        $t = ctype_digit($v) ? Tour::find((int) $v) : null;
        return $t !== null ? (int) $t['id'] : false;
    }

    public function create(): void
    {
        $tourId = $this->tourFromPost();
        [$values, $errors] = Hydrant::readForm($_POST, null);
        if ($tourId === false) {
            $errors[] = 'Die gewählte Tour gibt es nicht.';
        }
        $uploads = $this->collectUploads();
        $errors = array_merge($errors, $uploads['errors']);
        if ($errors) {
            $this->renderForm(null, Hydrant::rawForm($_POST), $errors, 422);
            return;
        }
        try {
            $id = Hydrant::insert($values);
            if ($tourId !== null && $tourId !== false) {
                Db::exec('UPDATE {hydrants} SET tour_id = ? WHERE id = ?', [$tourId, $id]);
            }
        } catch (\PDOException $e) {
            $this->renderForm(null, Hydrant::rawForm($_POST), ['Der Hydrant konnte nicht gespeichert werden (Kennung bereits vergeben?).'], 422);
            return;
        }
        Audit::log('hydrant_angelegt', 'hydrant', $id, (string) $values['kennung']);
        $msgs = $this->storeUploads($id, $uploads['files']);
        flash('success', 'Der Hydrant „' . $values['kennung'] . '“ wurde angelegt.');
        foreach ($msgs as $m) {
            flash('warning', $m);
        }
        redirect('hydranten/' . $id);
    }

    // ---- Detail ---------------------------------------------------------------

    public function show(array $params): void
    {
        $h = $this->load($params);
        if ($h === null) {
            return;
        }
        $this->renderShow($h, ['ergebnis' => '', 'maengel' => [], 'bemerkung' => ''], []);
    }

    /** Detailseite; wird auch nach einem Fehler beim Speichern einer Revision mit den bisherigen Eingaben angezeigt. */
    public function renderShow(array $h, array $revForm, array $revErrors, int $status = 200): void
    {
        $campaign = Campaign::active();
        $tour = $h['tour_id'] === null ? null : Tour::find((int) $h['tour_id']);
        $inCampaign = $campaign !== null && $tour !== null && CampaignTour::forTour((int) $campaign['id'], (int) $tour['id']) !== null;
        $current = null;
        if ($inCampaign) {
            $current = Db::one('SELECT id, ergebnis, revised_at FROM {revisions} WHERE hydrant_id = ? AND campaign_id = ? ORDER BY id DESC LIMIT 1', [(int) $h['id'], (int) $campaign['id']]);
        }
        $this->render('hydrants/show', [
            'title'   => (string) $h['kennung'],
            'h'       => $h,
            'campaign' => $campaign,
            'inCampaign' => $inCampaign,
            'tour'    => $tour,
            'current' => $current,
            'history' => Revision::history((int) $h['id']),
            'changeLog' => Auth::hasRole('admin') ? AuditLog::rows(['von' => '', 'bis' => '', 'gruppe' => '', 'benutzer' => '', 'q' => '', 'objekt' => 'hydrant:' . (int) $h['id'], 'seite' => 1], 20) : [],
            'defectList' => DefectType::forType((string) $h['typ']),
            'revForm' => $revForm,
            'revErrors' => $revErrors,
            'images'  => Hydrant::images((int) $h['id']),
            'others'  => $h['lat'] !== null && $h['lon'] !== null ? Hydrant::otherPoints((int) $h['id']) : [],
            'styles'  => ['vendor/leaflet/leaflet.css'],
            'scripts' => ['vendor/leaflet/leaflet.js', 'js/map.js', 'js/gallery.js', 'js/upload.js', 'js/revision.js'],
        ], $status);
    }

    // ---- Bearbeiten -----------------------------------------------------------

    public function editForm(array $params): void
    {
        $h = $this->load($params);
        if ($h === null) {
            return;
        }
        $this->renderForm($h, $this->formValues($h), []);
    }

    public function update(array $params): void
    {
        $h = $this->load($params);
        if ($h === null) {
            return;
        }
        $tourId = $this->tourFromPost();
        [$values, $errors] = Hydrant::readForm($_POST, $h);
        if ($tourId === false) {
            $errors[] = 'Die gewählte Tour gibt es nicht.';
        }
        $uploads = $this->collectUploads();
        $errors = array_merge($errors, $uploads['errors']);
        if ($errors) {
            $this->renderForm($h, Hydrant::rawForm($_POST), $errors, 422);
            return;
        }
        try {
            Hydrant::update((int) $h['id'], $values);
            if ($tourId !== false && ($h['tour_id'] === null ? null : (int) $h['tour_id']) !== $tourId) {
                Db::exec('UPDATE {hydrants} SET tour_id = ? WHERE id = ?', [$tourId, (int) $h['id']]);
                Audit::log('hydrant_tour_geaendert', 'hydrant', (int) $h['id'], (string) $h['kennung'] . ': Tour ' . ($tourId === null ? 'entfernt' : '#' . $tourId));
            }
        } catch (\PDOException $e) {
            $this->renderForm($h, Hydrant::rawForm($_POST), ['Der Hydrant konnte nicht gespeichert werden (Kennung bereits vergeben?).'], 422);
            return;
        }
        $diff = Hydrant::diff($h, $values);
        if ($diff !== '') {
            Audit::log('hydrant_geaendert', 'hydrant', (int) $h['id'], $diff);
        }
        $msgs = $this->storeUploads((int) $h['id'], $uploads['files']);
        flash('success', 'Die Änderungen wurden gespeichert.');
        foreach ($msgs as $m) {
            flash('warning', $m);
        }
        redirect('hydranten/' . (int) $h['id']);
    }

    /** Deaktivieren und wieder aktivieren (ein Löschen gibt es bewusst nicht). */
    public function toggleActive(array $params): void
    {
        $h = $this->load($params);
        if ($h === null) {
            return;
        }
        $new = (int) $h['aktiv'] === 1 ? 0 : 1;
        Db::exec('UPDATE {hydrants} SET aktiv = ?, updated_at = ? WHERE id = ?', [$new, now(), (int) $h['id']]);
        Audit::log($new === 1 ? 'hydrant_aktiviert' : 'hydrant_deaktiviert', 'hydrant', (int) $h['id'], (string) $h['kennung']);
        flash('success', $new === 1 ? 'Der Hydrant ist wieder aktiv.' : 'Der Hydrant wurde deaktiviert. Er erscheint nicht mehr in der Standardansicht, die Revisionen bleiben erhalten.');
        redirect('hydranten/' . (int) $h['id']);
    }

    // ---- Bilder ---------------------------------------------------------------

    public function addImages(array $params): void
    {
        $h = $this->load($params);
        if ($h === null) {
            return;
        }
        $uploads = $this->collectUploads();
        if ($uploads['errors']) {
            foreach ($uploads['errors'] as $e) {
                flash('error', $e);
            }
        } elseif (!$uploads['files']) {
            flash('error', 'Bitte mindestens ein Bild auswählen.');
        } else {
            foreach ($this->storeUploads((int) $h['id'], $uploads['files'], true) as $m) {
                flash($m[0], $m[1]);
            }
        }
        redirect('hydranten/' . (int) $h['id'] . '/bearbeiten');
    }

    /** Beschriftung ändern, Titelbild festlegen oder Bild entfernen. */
    public function imageAction(array $params): void
    {
        $h = $this->load($params);
        if ($h === null) {
            return;
        }
        $img = Db::one('SELECT id, datei, beschriftung FROM {hydrant_images} WHERE id = ? AND hydrant_id = ?', [(int) ($params['iid'] ?? 0), (int) $h['id']]);
        if ($img === null) {
            $this->fail(404, 'Nicht gefunden', 'Dieses Bild gibt es nicht.');
            return;
        }
        $action = $this->post('aktion');
        if ($action === 'beschriftung') {
            $text = mb_substr($this->post('beschriftung'), 0, 255);
            Db::exec('UPDATE {hydrant_images} SET beschriftung = ? WHERE id = ?', [$text === '' ? null : $text, (int) $img['id']]);
            Audit::log('bild_beschriftet', 'hydrant', (int) $h['id'], $text);
            flash('success', 'Die Beschriftung wurde gespeichert.');
        } elseif ($action === 'titelbild') {
            $min = (int) Db::val('SELECT MIN(sortierung) FROM {hydrant_images} WHERE hydrant_id = ?', [(int) $h['id']]);
            Db::exec('UPDATE {hydrant_images} SET sortierung = ? WHERE id = ?', [$min - 1, (int) $img['id']]);
            Audit::log('titelbild_gesetzt', 'hydrant', (int) $h['id'], (string) $img['datei']);
            flash('success', 'Das Bild ist jetzt das Titelbild.');
        } elseif ($action === 'entfernen') {
            Db::exec('DELETE FROM {hydrant_images} WHERE id = ?', [(int) $img['id']]);
            Images::deletePhoto((string) $img['datei']);
            Audit::log('bild_entfernt', 'hydrant', (int) $h['id'], (string) $img['datei']);
            flash('success', 'Das Bild wurde entfernt.');
        } else {
            flash('error', 'Unbekannte Aktion.');
        }
        redirect('hydranten/' . (int) $h['id'] . '/bearbeiten');
    }

    /** Liefert ein Bild nur an angemeldete Benutzer aus. */
    public function image(array $params): void
    {
        $size = (string) ($params['size'] ?? '');
        $row = Db::one('SELECT datei FROM {hydrant_images} WHERE id = ?', [(int) ($params['id'] ?? 0)]);
        if ($row === null || !in_array($size, ['thumb', 'full'], true)) {
            $this->fail(404, 'Nicht gefunden', 'Dieses Bild gibt es nicht.');
            return;
        }
        if (!Images::serve((string) $row['datei'], $size)) {
            $this->fail(404, 'Nicht gefunden', 'Die Bilddatei fehlt.');
        }
    }

    // ---- intern ---------------------------------------------------------------

    private function query(string $key, string $default = ''): string
    {
        $v = $_GET[$key] ?? $default;
        return is_string($v) ? trim($v) : $default;
    }

    private function load(array $params): ?array
    {
        $h = Hydrant::find((int) ($params['id'] ?? 0));
        if ($h === null) {
            $this->fail(404, 'Nicht gefunden', 'Diesen Hydranten gibt es nicht.');
        }
        return $h;
    }

    /** Werte in die Schreibweise der Formularfelder bringen. */
    private function formValues(array $h): array
    {
        return Hydrant::formValues($h) + ['tour_id' => $h['tour_id'] === null ? '' : (string) $h['tour_id']];
    }

    private function renderForm(?array $h, array $form, array $errors, int $status = 200): void
    {
        // Kartenmitte für neue Hydranten: Mittelpunkt der vorhandenen Standorte
        // Vorrang hat die in den Einstellungen festgelegte Kartenmitte.
        $def = Settings::mapDefault();
        $avg = $def === null ? Db::one('SELECT AVG(lat) AS lat, AVG(lon) AS lon FROM {hydrants} WHERE lat IS NOT NULL AND lon IS NOT NULL') : null;
        $center = $def !== null ? [(float) $def[0], (float) $def[1]] : (($avg && $avg['lat'] !== null) ? [(float) $avg['lat'], (float) $avg['lon']] : null);
        $this->render('hydrants/form', [
            'title'   => $h === null ? 'Hydrant anlegen' : 'Hydrant bearbeiten',
            'h'       => $h,
            'form'    => $form,
            'errors'  => $errors,
            'images'  => $h === null ? [] : Hydrant::images((int) $h['id']),
            'center'  => $center,
            'others'  => Hydrant::otherPoints($h === null ? null : (int) $h['id']),
            'tours'   => Tour::all(),
            'zoom'    => $def !== null ? $def[2] : 16,
            'styles'  => ['vendor/leaflet/leaflet.css'],
            'scripts' => ['vendor/leaflet/leaflet.js', 'js/map.js', 'js/upload.js'],
        ], $status);
    }

    /**
     * Hochgeladene Bilder aus $_FILES['bilder'] einsammeln und grob prüfen.
     * @return array{files: array<int, array{name:string, tmp:string}>, errors: string[]}
     */
    private function collectUploads(): array
    {
        $files = [];
        $errors = [];
        $in = $_FILES['bilder'] ?? null;
        if (!is_array($in) || !is_array($in['name'] ?? null)) {
            return ['files' => [], 'errors' => []];
        }
        foreach ($in['name'] as $i => $name) {
            $err = (int) ($in['error'][$i] ?? UPLOAD_ERR_NO_FILE);
            if ($err === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $label = '„' . mb_substr((string) $name, 0, 60) . '“';
            if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
                $errors[] = 'Bild ' . $label . ' ist für den Server zu groß (erlaubt: ' . ini_get('upload_max_filesize') . ').';
            } elseif ($err !== UPLOAD_ERR_OK) {
                $errors[] = 'Bild ' . $label . ' konnte nicht hochgeladen werden (Fehlercode ' . $err . ').';
            } elseif (!is_uploaded_file((string) $in['tmp_name'][$i])) {
                $errors[] = 'Ungültiger Upload.';
            } else {
                $files[] = ['name' => (string) $name, 'tmp' => (string) $in['tmp_name'][$i]];
            }
        }
        if (count($files) > 10) {
            $errors[] = 'Bitte höchstens 10 Bilder auf einmal hochladen.';
        }
        return ['files' => $files, 'errors' => $errors];
    }

    /**
     * Verarbeitet und speichert die Bilder eines Hydranten.
     * @param bool $typed true: Rückgabe als [Art, Text]-Paare, sonst nur Texte (für Warnungen)
     * @return array<int, mixed>
     */
    private function storeUploads(int $hydrantId, array $files, bool $typed = false): array
    {
        $out = [];
        $count = (int) Db::val('SELECT COUNT(*) FROM {hydrant_images} WHERE hydrant_id = ?', [$hydrantId]);
        $max = (int) Db::val('SELECT MAX(sortierung) FROM {hydrant_images} WHERE hydrant_id = ?', [$hydrantId]);
        $saved = 0;
        foreach ($files as $file) {
            $label = '„' . mb_substr($file['name'], 0, 60) . '“';
            if ($count >= Hydrant::MAX_IMAGES) {
                $msg = 'Bild ' . $label . ' wurde nicht gespeichert: Pro Hydrant sind höchstens ' . Hydrant::MAX_IMAGES . ' Bilder möglich.';
                $out[] = $typed ? ['warning', $msg] : $msg;
                continue;
            }
            [$name, $err] = Images::storePhoto($file['tmp']);
            if ($name === null) {
                $msg = 'Bild ' . $label . ': ' . $err;
                $out[] = $typed ? ['error', $msg] : $msg;
                continue;
            }
            $max++;
            Db::insert(
                'INSERT INTO {hydrant_images} (hydrant_id, datei, beschriftung, sortierung, user_id, created_at) VALUES (?, ?, NULL, ?, ?, ?)',
                [$hydrantId, $name, $max, Auth::user()['id'] ?? null, now()]
            );
            $count++;
            $saved++;
        }
        if ($saved > 0) {
            Audit::log('bilder_hochgeladen', 'hydrant', $hydrantId, $saved . ' Bild(er)');
            if ($typed) {
                $out[] = ['success', $saved === 1 ? 'Das Bild wurde gespeichert.' : $saved . ' Bilder wurden gespeichert.'];
            }
        }
        return $out;
    }
}
