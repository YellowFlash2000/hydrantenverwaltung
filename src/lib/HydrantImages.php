<?php
declare(strict_types=1);

/**
 * Bilder eines Hydranten: Sichtbarkeit (Vorschläge sehen nur der Hochladende und Bearbeiter),
 * Hochladen mit Markierung, Freigabe, Ablehnung und Veraltet-Meldung.
 *
 * Eine Markierung ist ein gelbes Oval, gespeichert als Anteile der Bildgröße:
 * Mittelpunkt (x, y) und Radien (rx, ry), jeweils 0 bis 1 (Radien höchstens 0,5).
 */
final class HydrantImages
{
    public const MAX_PER_UPLOAD = 10;
    public const MAX_PER_SUGGESTION = 6;
    public const MARKER_MIN = 0.02;
    public const MARKER_MAX = 0.5;

    public const OK = 'ok';
    public const PROPOSAL = 'vorschlag';

    private const COLUMNS = 'id, hydrant_id, datei, beschriftung, sortierung, created_at, status, quelle, user_id, outing_id, ersetzt_id,'
        . ' vorgeschlagen_von, veraltet_at, veraltet_von, marker_x, marker_y, marker_rx, marker_ry';

    /** "x,y,rx,ry" prüfen. @return array{x:float,y:float,rx:float,ry:float}|null */
    public static function parseMarker(mixed $raw): ?array
    {
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }
        $parts = explode(',', trim($raw));
        if (count($parts) !== 4) {
            return null;
        }
        $v = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if (preg_match('/^\d{1,3}(\.\d{1,8})?$/', $p) !== 1) {
                return null;
            }
            $v[] = (float) $p;
        }
        [$x, $y, $rx, $ry] = $v;
        if ($x < 0 || $x > 1 || $y < 0 || $y > 1 || $rx < self::MARKER_MIN || $rx > self::MARKER_MAX || $ry < self::MARKER_MIN || $ry > self::MARKER_MAX) {
            return null;
        }
        return ['x' => $x, 'y' => $y, 'rx' => $rx, 'ry' => $ry];
    }

    /** Markierung eines Bildes als "x,y,rx,ry" (für data-marker) oder leer. */
    public static function markerAttr(array $img): string
    {
        if (($img['marker_x'] ?? null) === null || ($img['marker_y'] ?? null) === null || ($img['marker_rx'] ?? null) === null || ($img['marker_ry'] ?? null) === null) {
            return '';
        }
        $f = static fn ($v): string => rtrim(rtrim(number_format((float) $v, 5, '.', ''), '0'), '.') ?: '0';
        return $f($img['marker_x']) . ',' . $f($img['marker_y']) . ',' . $f($img['marker_rx']) . ',' . $f($img['marker_ry']);
    }

    /** @return array<int, string> Spaltenwerte für die Markierung (null = keine) */
    private static function markerValues(?array $m): array
    {
        if ($m === null) {
            return [null, null, null, null];
        }
        $f = static fn (float $v): string => number_format($v, 5, '.', '');
        return [$f($m['x']), $f($m['y']), $f($m['rx']), $f($m['ry'])];
    }

    /**
     * Bilder, die der Betrachter sehen darf.
     * @param bool $all true: alles (Bearbeiter), sonst freigegebene Bilder plus eigene Vorschläge
     * @return array<int, array<string, mixed>>
     */
    public static function visible(int $hydrantId, bool $all = false, ?int $userId = null, ?int $outingId = null): array
    {
        $sql = 'SELECT ' . self::COLUMNS . ' FROM {hydrant_images} WHERE hydrant_id = ?';
        $params = [$hydrantId];
        if (!$all) {
            $sql .= " AND (status = 'ok'";
            if ($userId !== null) {
                $sql .= " OR (status = 'vorschlag' AND user_id = ?)";
                $params[] = $userId;
            }
            if ($outingId !== null) {
                $sql .= " OR (status = 'vorschlag' AND outing_id = ?)";
                $params[] = $outingId;
            }
            $sql .= ')';
        }
        return Db::all($sql . ' ORDER BY sortierung, id', $params);
    }

    /** Darf der Betrachter dieses Bild abrufen? */
    public static function mayView(array $img, bool $all, ?int $userId, ?int $outingId): bool
    {
        if ($all || ($img['status'] ?? self::OK) === self::OK) {
            return true;
        }
        return ($userId !== null && (int) ($img['user_id'] ?? 0) === $userId)
            || ($outingId !== null && (int) ($img['outing_id'] ?? 0) === $outingId);
    }

    /**
     * Hochgeladene Bilder aus $_FILES['bilder'] einsammeln und grob prüfen.
     * Jede Datei behält ihre Position im Formular ('idx'), damit die Markierungen zugeordnet werden können.
     * @return array{files: array<int, array{name:string, tmp:string, idx:int}>, errors: string[]}
     */
    public static function collectUploads(int $max = self::MAX_PER_UPLOAD): array
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
                $files[] = ['name' => (string) $name, 'tmp' => (string) $in['tmp_name'][$i], 'idx' => (int) $i];
            }
        }
        if (count($files) > $max) {
            $errors[] = 'Bitte höchstens ' . $max . ' Bilder auf einmal hochladen.';
        }
        return ['files' => $files, 'errors' => $errors];
    }

    /** Markierungen aus $_POST['marker'][Index] für die eingesammelten Dateien. @return array<int, ?array> nach Index */
    public static function postedMarkers(): array
    {
        $out = [];
        $in = $_POST['marker'] ?? null;
        if (is_array($in)) {
            foreach ($in as $i => $raw) {
                $out[(int) $i] = self::parseMarker($raw);
            }
        }
        return $out;
    }

    /**
     * Bilder verarbeiten und speichern.
     * @param array<int, array{name:string, tmp:string, idx?:int}> $files
     * @param array{status?:string, quelle?:string, user_id?:?int, outing_id?:?int, von?:?string, ersetzt_id?:?int} $opt
     * @return array{0:int, 1:array<int, array{0:string,1:string}>} [gespeichert, Meldungen als [Art, Text]]
     */
    public static function store(int $hydrantId, array $files, array $opt = []): array
    {
        $status = ($opt['status'] ?? self::OK) === self::PROPOSAL ? self::PROPOSAL : self::OK;
        $markers = self::postedMarkers();
        $msgs = [];
        $count = (int) Db::val('SELECT COUNT(*) FROM {hydrant_images} WHERE hydrant_id = ?', [$hydrantId]);
        $max = (int) Db::val('SELECT MAX(sortierung) FROM {hydrant_images} WHERE hydrant_id = ?', [$hydrantId]);
        $saved = 0;
        foreach ($files as $pos => $file) {
            $label = '„' . mb_substr($file['name'], 0, 60) . '“';
            if ($count >= Hydrant::MAX_IMAGES) {
                $msgs[] = ['warning', 'Bild ' . $label . ' wurde nicht gespeichert: Pro Hydrant sind höchstens ' . Hydrant::MAX_IMAGES . ' Bilder möglich.'];
                continue;
            }
            [$name, $err] = Images::storePhoto($file['tmp']);
            if ($name === null) {
                $msgs[] = ['error', 'Bild ' . $label . ': ' . $err];
                continue;
            }
            $max++;
            [$mx, $my, $mrx, $mry] = self::markerValues($markers[$file['idx'] ?? $pos] ?? null);
            Db::insert(
                'INSERT INTO {hydrant_images} (hydrant_id, datei, beschriftung, sortierung, user_id, created_at, status, quelle, outing_id, ersetzt_id,'
                . ' vorgeschlagen_von, marker_x, marker_y, marker_rx, marker_ry) VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $hydrantId, $name, $max, $opt['user_id'] ?? null, now(), $status, (string) ($opt['quelle'] ?? 'app'),
                    $opt['outing_id'] ?? null, $status === self::PROPOSAL ? ($opt['ersetzt_id'] ?? null) : null,
                    $status === self::PROPOSAL ? ($opt['von'] ?? null) : null, $mx, $my, $mrx, $mry,
                ]
            );
            $count++;
            $saved++;
        }
        return [$saved, $msgs];
    }

    /** Das Bild, das ein Vorschlag ersetzen soll (muss freigegeben sein und zum Hydranten gehören), sonst null. */
    public static function replaceTarget(int $hydrantId, mixed $raw): ?int
    {
        $id = is_scalar($raw) ? (int) $raw : 0;
        if ($id <= 0) {
            return null;
        }
        $row = Db::one("SELECT id FROM {hydrant_images} WHERE id = ? AND hydrant_id = ? AND status = 'ok'", [$id, $hydrantId]);
        return $row === null ? null : (int) $row['id'];
    }

    /** Freigabe: Der Vorschlag wird sichtbar; ersetzt er ein Bild, wird er Titelbild und das alte gilt als veraltet. @return string Meldung */
    public static function approve(array $img): string
    {
        $hid = (int) $img['hydrant_id'];
        Db::exec("UPDATE {hydrant_images} SET status = 'ok' WHERE id = ?", [(int) $img['id']]);
        $text = 'Das Bild wurde freigegeben.';
        $old = $img['ersetzt_id'] === null ? null : Db::one("SELECT id, veraltet_at FROM {hydrant_images} WHERE id = ? AND hydrant_id = ?", [(int) $img['ersetzt_id'], $hid]);
        if ($old !== null) {
            $min = (int) Db::val('SELECT MIN(sortierung) FROM {hydrant_images} WHERE hydrant_id = ?', [$hid]);
            Db::exec('UPDATE {hydrant_images} SET sortierung = ? WHERE id = ?', [$min - 1, (int) $img['id']]);
            if ($old['veraltet_at'] === null) {
                Db::exec('UPDATE {hydrant_images} SET veraltet_at = ?, veraltet_von = ? WHERE id = ?', [now(), 'ersetzt durch neues Bild', (int) $old['id']]);
            }
            $text = 'Das Bild wurde freigegeben und ist jetzt das Titelbild. Das ersetzte Bild ist als veraltet markiert und kann entfernt werden.';
        }
        return $text;
    }

    /** Ein Bild samt Dateien löschen; Verweise anderer Vorschläge darauf werden gelöst. */
    public static function delete(array $img): void
    {
        Db::exec('UPDATE {hydrant_images} SET ersetzt_id = NULL WHERE ersetzt_id = ?', [(int) $img['id']]);
        Db::exec('DELETE FROM {hydrant_images} WHERE id = ?', [(int) $img['id']]);
        Images::deletePhoto((string) $img['datei']);
    }

    /** Bild als veraltet melden. @return bool false, wenn es nicht möglich ist (kein freigegebenes Bild) */
    public static function reportOutdated(array $img, string $by): bool
    {
        if (($img['status'] ?? '') !== self::OK) {
            return false;
        }
        if ($img['veraltet_at'] === null) {
            Db::exec('UPDATE {hydrant_images} SET veraltet_at = ?, veraltet_von = ? WHERE id = ?', [now(), mb_substr($by, 0, 255), (int) $img['id']]);
        }
        return true;
    }

    /** Markierung eines Bildes setzen (null = entfernen). */
    public static function setMarker(int $imageId, ?array $m): void
    {
        [$x, $y, $rx, $ry] = self::markerValues($m);
        Db::exec('UPDATE {hydrant_images} SET marker_x = ?, marker_y = ?, marker_rx = ?, marker_ry = ? WHERE id = ?', [$x, $y, $rx, $ry, $imageId]);
    }

    /** Anzahl Hydranten mit Vorschlägen bzw. gemeldeten veralteten Bildern. @return array{neu:int, alt:int} */
    public static function openCounts(): array
    {
        return [
            'neu' => (int) Db::val("SELECT COUNT(DISTINCT hydrant_id) FROM {hydrant_images} WHERE status = 'vorschlag'"),
            'alt' => (int) Db::val("SELECT COUNT(DISTINCT hydrant_id) FROM {hydrant_images} WHERE status = 'ok' AND veraltet_at IS NOT NULL"),
        ];
    }
}
