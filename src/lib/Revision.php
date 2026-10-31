<?php
declare(strict_types=1);

/** Revisionen (Prüfungen) an Hydranten: Ergebnis OK oder Mangel, Mängel, Fotos. */
final class Revision
{
    public const OK = 'ok';
    public const DEFECT = 'mangel';
    public const MAX_PHOTOS = 6;

    /**
     * Liest hochgeladene Fotos eines Formularfeldes.
     * @return array{files: array<int, array{name:string, tmp:string}>, errors: string[]}
     */
    public static function collectPhotos(string $field): array
    {
        $files = [];
        $errors = [];
        $in = $_FILES[$field] ?? null;
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
                $errors[] = 'Foto ' . $label . ' ist für den Server zu groß (erlaubt: ' . ini_get('upload_max_filesize') . ').';
            } elseif ($err !== UPLOAD_ERR_OK) {
                $errors[] = 'Foto ' . $label . ' konnte nicht hochgeladen werden (Fehlercode ' . $err . ').';
            } elseif (!is_uploaded_file((string) $in['tmp_name'][$i])) {
                $errors[] = 'Ungültiger Upload.';
            } else {
                $files[] = ['name' => (string) $name, 'tmp' => (string) $in['tmp_name'][$i]];
            }
        }
        if (count($files) > self::MAX_PHOTOS) {
            $errors[] = 'Bitte höchstens ' . self::MAX_PHOTOS . ' Fotos je Mangel hochladen.';
        }
        return ['files' => $files, 'errors' => $errors];
    }

    /**
     * Verarbeitet die Fotos (verkleinern, neu speichern). Bei einem Fehler wird nichts behalten.
     * @return array{0: string[], 1: string[]} [Dateinamen, Fehlermeldungen]
     */
    public static function storePhotos(array $files): array
    {
        $names = [];
        $errors = [];
        foreach ($files as $f) {
            [$name, $err] = Images::storePhoto($f['tmp']);
            if ($name === null) {
                $errors[] = 'Foto „' . mb_substr($f['name'], 0, 60) . '“: ' . $err;
            } else {
                $names[] = $name;
            }
        }
        if ($errors) {
            foreach ($names as $n) {
                Images::deletePhoto($n);
            }
            return [[], $errors];
        }
        return [$names, []];
    }

    /** Alle Revisionen eines Hydranten, neueste zuerst, mit Mängeln, Fotos, Durchgang, Tour und Teilnehmern. */
    public static function history(int $hydrantId): array
    {
        $rows = Db::all(
            'SELECT r.*, c.name AS campaign_name, t.name AS tour_name, o.teilnehmer, u.name AS user_name'
            . ' FROM {revisions} r'
            . ' LEFT JOIN {campaigns} c ON c.id = r.campaign_id'
            . ' LEFT JOIN {tours} t ON t.id = r.tour_id'
            . ' LEFT JOIN {tour_outings} o ON o.id = r.outing_id'
            . ' LEFT JOIN {users} u ON u.id = r.user_id'
            . ' WHERE r.hydrant_id = ? ORDER BY r.id DESC',
            [$hydrantId]
        );
        foreach ($rows as &$r) {
            $r['defects'] = array_map(
                static fn ($d) => (string) $d['name'],
                Db::all('SELECT d.name FROM {revision_defects} rd JOIN {defect_types} d ON d.id = rd.defect_type_id WHERE rd.revision_id = ? ORDER BY d.sortierung, d.id', [(int) $r['id']])
            );
            $r['images'] = Db::all('SELECT id FROM {revision_images} WHERE revision_id = ? ORDER BY id', [(int) $r['id']]);
        }
        unset($r);
        return $rows;
    }

    /**
     * Liest und prüft die Eingaben einer Revision.
     * @return array{0: array<string, mixed>, 1: string[]} [Werte, Fehler]
     */
    public static function readForm(array $post, array $hydrant): array
    {
        $errors = [];
        $result = is_string($post['ergebnis'] ?? null) ? $post['ergebnis'] : '';
        if (!in_array($result, [self::OK, self::DEFECT], true)) {
            $errors[] = 'Bitte das Ergebnis wählen: in Ordnung oder Mangel.';
            $result = '';
        }
        $note = is_string($post['bemerkung'] ?? null) ? trim($post['bemerkung']) : '';
        if (mb_strlen($note) > 2000) {
            $errors[] = 'Die Bemerkung ist zu lang (höchstens 2000 Zeichen).';
        }
        $defects = [];
        if ($result === self::DEFECT) {
            $ids = is_array($post['maengel'] ?? null) ? $post['maengel'] : [];
            $defects = DefectType::filterAllowed((string) $hydrant['typ'], $ids);
            if ($defects === [] && $note === '') {
                $errors[] = 'Bei einem Mangel bitte mindestens einen Mangel auswählen oder eine Bemerkung eintragen.';
            }
        }
        return [['ergebnis' => $result, 'bemerkung' => $note === '' ? null : $note, 'defects' => $defects], $errors];
    }

    /**
     * Speichert eine Revision samt Mängeln und Fotos.
     * @param array<int, string> $photoFiles bereits verarbeitete Dateinamen (nur bei Mangel)
     */
    public static function create(array $hydrant, array $values, string $source, ?int $userId, ?int $outingId, ?array $tour, ?array $campaign, array $photoFiles): int
    {
        $now = now();
        $tourId = $tour['id'] ?? null;
        $campaignId = $campaign['id'] ?? null;
        $id = Db::insert(
            'INSERT INTO {revisions} (hydrant_id, campaign_id, tour_id, outing_id, user_id, quelle, ergebnis, bemerkung, revised_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [(int) $hydrant['id'], $campaignId === null ? null : (int) $campaignId, $tourId === null ? null : (int) $tourId, $outingId, $userId, $source, $values['ergebnis'], $values['bemerkung'], $now, $now]
        );
        foreach ($values['defects'] as $defectId) {
            Db::exec('INSERT INTO {revision_defects} (revision_id, defect_type_id) VALUES (?, ?)', [$id, (int) $defectId]);
        }
        foreach ($photoFiles as $file) {
            Db::exec('INSERT INTO {revision_images} (revision_id, datei, created_at) VALUES (?, ?, ?)', [$id, $file, $now]);
        }
        return $id;
    }
}
