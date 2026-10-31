<?php
declare(strict_types=1);

/** CSV lesen und schreiben, Zeichen reparieren (HTML-Zeichen, Zeichensatz). */
final class Csv
{
    public const MAX_BYTES = 5 * 1024 * 1024;
    public const MAX_ROWS = 5000;

    /** Rohdaten einer Datei nach UTF-8 bringen (UTF-8 mit/ohne BOM, sonst Windows-1252 wie bei Excel). */
    public static function toUtf8(string $raw): string
    {
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        } elseif (str_starts_with($raw, "\xFF\xFE") || str_starts_with($raw, "\xFE\xFF")) {
            $raw = (string) mb_convert_encoding($raw, 'UTF-8', 'UTF-16');
        }
        if (!mb_check_encoding($raw, 'UTF-8')) {
            $raw = (string) mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }
        return str_replace("\0", '', $raw);
    }

    /**
     * Liest CSV-Text (UTF-8).
     * @return array{0: string[], 1: array<int, array{line: int, cells: string[]}>} [Kopfzeile, Datenzeilen]
     */
    public static function parse(string $text): array
    {
        $firstLine = strtok($text, "\r\n") ?: '';
        $best = ';';
        $bestCount = -1;
        foreach ([';', ',', "\t"] as $d) {
            $c = substr_count($firstLine, $d);
            if ($c > $bestCount) {
                $best = $d;
                $bestCount = $c;
            }
        }
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $text);
        rewind($fh);
        $header = null;
        $rows = [];
        $n = 0;
        while (($cells = fgetcsv($fh, 0, $best, '"', '')) !== false) {
            $n++;
            if ($cells === [null]) {
                continue; // Leerzeile
            }
            $cells = array_map(static fn ($c) => (string) $c, $cells);
            if ($header === null) {
                $header = $cells;
                continue;
            }
            if (count($rows) >= self::MAX_ROWS) {
                break;
            }
            $rows[] = ['line' => $n, 'cells' => $cells];
        }
        fclose($fh);
        return [$header ?? [], $rows];
    }

    /** Spaltenüberschrift vereinheitlichen (klein, ohne Umlaute, Sonderzeichen als Unterstrich). */
    public static function headerKey(string $h): string
    {
        $h = mb_strtolower(trim(self::clean($h)));
        $h = strtr($h, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
        return trim((string) preg_replace('/[^a-z0-9]+/', '_', $h), '_');
    }

    /** Schlüssel zum Vergleichen von Texten (Groß-/Kleinschreibung, Umlaute, Leerzeichen egal). */
    public static function compareKey(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
        return (string) preg_replace('/\s+/', ' ', $s);
    }

    /**
     * Text aus einer Zelle aufbereiten: HTML-Zeichen (&uuml; &#252; &amp; ...) in lesbare Zeichen
     * umwandeln, verdrehte Umlaute (Ã¼) reparieren, Steuerzeichen entfernen.
     */
    public static function clean(string $s): string
    {
        $s = str_replace(["\r\n", "\r"], "\n", $s);
        for ($i = 0; $i < 3 && str_contains($s, '&'); $i++) {
            $d = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($d === $s) {
                break;
            }
            $s = $d;
        }
        // UTF-8, das einmal fälschlich als Windows-1252 gelesen wurde ("Ã¼" statt "ü"): nur diese Folgen zurückwandeln
        $cont = '\x{0080}-\x{00BF}\x{0152}\x{0153}\x{0160}\x{0161}\x{0178}\x{017D}\x{017E}\x{0192}\x{02C6}\x{02DC}\x{2013}\x{2014}\x{2018}-\x{201E}\x{2020}-\x{2022}\x{2026}\x{2030}\x{2039}\x{203A}\x{20AC}\x{2122}';
        $s = (string) preg_replace_callback('/[\x{00C3}\x{00C2}][' . $cont . ']|\x{00E2}[' . $cont . ']{2}/u', static function (array $m): string {
            $bytes = mb_convert_encoding($m[0], 'Windows-1252', 'UTF-8');
            return is_string($bytes) && !str_contains($bytes, '?') && mb_check_encoding($bytes, 'UTF-8') ? $bytes : $m[0];
        }, $s);
        $s = str_replace("\xC2\xA0", ' ', $s); // geschütztes Leerzeichen
        $s = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $s);
        $s = trim($s);
        // Schutzzeichen gegen Formeln in Excel (beim Export gesetzt) wieder entfernen
        if (preg_match("/^'[=+@\-]/", $s) === 1) {
            $s = substr($s, 1);
        }
        return $s;
    }

    /** Zelle für die Ausgabe: Formelschutz und Anführungszeichen. */
    private static function cell(string $v): string
    {
        if ($v !== '' && preg_match('/^[=+@]/', $v) === 1) {
            $v = "'" . $v;
        } elseif ($v !== '' && $v[0] === '-' && preg_match('/^-\d+([.,]\d+)?$/', $v) !== 1) {
            $v = "'" . $v;
        }
        if ($v === '') {
            return '';
        }
        if (preg_match('/[";\r\n,]/', $v) === 1 || $v !== trim($v)) {
            return '"' . str_replace('"', '""', $v) . '"';
        }
        return $v;
    }

    /** CSV (Semikolon, UTF-8 mit BOM, damit Excel Umlaute richtig zeigt). */
    public static function write(array $header, array $rows): string
    {
        $out = "\xEF\xBB\xBF" . implode(';', array_map([self::class, 'cell'], $header)) . "\r\n";
        foreach ($rows as $r) {
            $out .= implode(';', array_map(static fn ($v) => self::cell((string) ($v ?? '')), $r)) . "\r\n";
        }
        return $out;
    }
}
