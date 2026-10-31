<?php
declare(strict_types=1);

/** Bilder-Import: Dateien liegen im Importordner (per FTP), die CSV ordnet sie Hydranten zu. */
final class ImageImport
{
    public const COLUMNS = ['kennung', 'datei', 'beschriftung'];
    public const EXT = ['jpg', 'jpeg', 'png', 'webp'];

    private const ALIASES = [
        'kennung'      => ['kennung', 'hydrantenkennung', 'hydrant', 'hydrant_kennung'],
        'datei'        => ['datei', 'dateiname', 'bild', 'bilddatei', 'file', 'filename'],
        'beschriftung' => ['beschriftung', 'bildunterschrift', 'titel', 'caption'],
    ];

    public static function dir(): string
    {
        return STORAGE . '/import';
    }

    /** Dateien im Importordner: [kleingeschriebener Name => tatsächlicher Name]. @return array<string, string> */
    public static function files(): array
    {
        $out = [];
        $dir = self::dir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        foreach (is_dir($dir) ? (scandir($dir) ?: []) : [] as $f) {
            if ($f === '.' || $f === '..' || !is_file($dir . '/' . $f)) {
                continue;
            }
            $out[mb_strtolower($f)] = $f;
        }
        return $out;
    }

    /** Fehlt dem Importordner die Berechtigung zum Löschen/Schreiben? */
    public static function dirWritable(): bool
    {
        return is_dir(self::dir()) && is_writable(self::dir());
    }

    /**
     * Liest die Zuordnungs-CSV.
     * @return array{fatal: ?string, rows: array<int, array<string, mixed>>}
     */
    public static function parse(string $text): array
    {
        [$header, $rows] = Csv::parse($text);
        $map = [];
        foreach ($header as $i => $h) {
            $key = Csv::headerKey($h);
            foreach (self::ALIASES as $field => $aliases) {
                if (in_array($key, $aliases, true) && !in_array($field, $map, true)) {
                    $map[$i] = $field;
                }
            }
        }
        foreach (['kennung', 'datei'] as $need) {
            if (!in_array($need, $map, true)) {
                return ['fatal' => 'In der Datei fehlt die Spalte „' . $need . '“. Bitte die Überschriftenzeile prüfen oder die Mustervorlage verwenden.', 'rows' => []];
            }
        }
        $out = [];
        foreach ($rows as $r) {
            $v = ['kennung' => '', 'datei' => '', 'beschriftung' => ''];
            foreach ($map as $i => $field) {
                $v[$field] = Csv::clean($r['cells'][$i] ?? '');
            }
            if ($v['kennung'] . $v['datei'] . $v['beschriftung'] === '') {
                continue;
            }
            $v['kennung'] = HydrantImport::cleanKennung($v['kennung']);
            $v['line'] = $r['line'];
            $out[] = $v;
        }
        return ['fatal' => $out ? null : 'Die Datei enthält keine Datenzeilen.', 'rows' => $out];
    }

    /**
     * Prüft eine Zeile gegen Datenbank und Importordner.
     * @param array<string, string> $files Ergebnis von files()
     * @return array{hydrant: ?array, file: ?string, error: ?string}
     */
    public static function check(array $row, array $files, array $pendingPerHydrant = []): array
    {
        $res = ['hydrant' => null, 'file' => null, 'error' => null];
        if ($row['kennung'] === '') {
            $res['error'] = 'Die Kennung fehlt.';
            return $res;
        }
        $h = Db::one('SELECT id, kennung FROM {hydrants} WHERE kennung = ?', [$row['kennung']]);
        if ($h === null) {
            $res['error'] = 'Kein Hydrant mit dieser Kennung.';
            return $res;
        }
        $res['hydrant'] = $h;
        $name = (string) $row['datei'];
        if ($name === '') {
            $res['error'] = 'Der Dateiname fehlt.';
            return $res;
        }
        if ($name !== basename($name) || str_contains($name, '\\') || str_starts_with($name, '.')) {
            $res['error'] = 'Der Dateiname darf keinen Ordner enthalten.';
            return $res;
        }
        if (!in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), self::EXT, true)) {
            $res['error'] = 'Dateityp nicht erlaubt (erlaubt: JPG, PNG, WebP).';
            return $res;
        }
        $actual = $files[mb_strtolower($name)] ?? null;
        if ($actual === null) {
            $res['error'] = 'Die Datei liegt nicht im Importordner.';
            return $res;
        }
        if (@getimagesize(self::dir() . '/' . $actual) === false) {
            $res['error'] = 'Die Datei ist kein gültiges Bild.';
            return $res;
        }
        $res['file'] = $actual;
        $have = (int) Db::val('SELECT COUNT(*) FROM {hydrant_images} WHERE hydrant_id = ?', [(int) $h['id']]) + ($pendingPerHydrant[(int) $h['id']] ?? 0);
        if ($have >= Hydrant::MAX_IMAGES) {
            $res['error'] = 'Der Hydrant hat schon ' . Hydrant::MAX_IMAGES . ' Bilder.';
            $res['file'] = null;
        }
        return $res;
    }

    /** Verarbeitet eine Zeile. @return array{0: bool, 1: string} [Erfolg, Meldung] */
    public static function process(array $row): array
    {
        $files = self::files();
        $c = self::check($row, $files);
        if ($c['error'] !== null || $c['file'] === null || $c['hydrant'] === null) {
            return [false, $c['error'] ?? 'Nicht verarbeitbar.'];
        }
        $path = self::dir() . '/' . $c['file'];
        [$name, $err] = Images::storePhoto($path);
        if ($name === null) {
            return [false, (string) $err];
        }
        $hid = (int) $c['hydrant']['id'];
        $max = (int) Db::val('SELECT COALESCE(MAX(sortierung), 0) FROM {hydrant_images} WHERE hydrant_id = ?', [$hid]);
        $caption = mb_substr((string) $row['beschriftung'], 0, 255);
        Db::insert(
            'INSERT INTO {hydrant_images} (hydrant_id, datei, beschriftung, sortierung, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$hid, $name, $caption === '' ? null : $caption, $max + 1, Auth::user()['id'] ?? null, now()]
        );
        if (!@unlink($path)) {
            return [true, 'Importiert, aber die Datei ließ sich nicht aus dem Importordner entfernen. Bitte per FTP löschen, sonst wird sie bei einem erneuten Import doppelt übernommen.'];
        }
        return [true, 'Importiert.'];
    }

    public static function sample(): string
    {
        return Csv::write(self::COLUMNS, [
            ['Musterstraße 1', 'musterstrasse_1.jpg', 'Gesamtansicht'],
            ['Musterstraße 1', 'musterstrasse_1_deckel.jpg', 'Deckel'],
            ['Musterweg 12', 'musterweg_12.jpg', ''],
        ]);
    }
}
