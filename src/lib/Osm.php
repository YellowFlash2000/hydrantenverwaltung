<?php
declare(strict_types=1);

/** Hydranten aus einer OpenStreetMap-Exportdatei (Overpass Turbo) für den Import aufbereiten. Daten: © OpenStreetMap-Mitwirkende (ODbL). */
final class Osm
{
    /** Hydranten näher als so viele Meter an einem vorhandenen Hydranten gelten als schon vorhanden. */
    public const NEAR_METERS = 15;

    /** Felder, aus denen die Kennung zusammengesetzt werden kann. */
    public const KENNUNG_FIELDS = ['ref' => 'Referenznummer (ref)', 'location' => 'Lage (fire_hydrant:location)', 'node' => 'OSM-Knoten'];
    /** Trennzeichen zwischen den Teilen der Kennung. */
    public const SEPARATORS = ['strich' => ' – ', 'schraeg' => ' / ', 'komma' => ', ', 'leer' => ' ', 'bindestrich' => '-', 'unterstrich' => '_'];
    public const SEPARATOR_LABELS = ['strich' => 'Gedankenstrich ( – )', 'schraeg' => 'Schrägstrich ( / )', 'komma' => 'Komma ( , )', 'leer' => 'Leerzeichen', 'bindestrich' => 'Bindestrich (-)', 'unterstrich' => 'Unterstrich (_)'];
    /** Angaben, die in die Bemerkung geschrieben werden können. */
    public const NOTE_FIELDS = ['ref' => 'Referenznummer (ref)', 'location' => 'Lage (fire_hydrant:location)', 'note' => 'Notiz aus OSM (note/description)', 'src' => 'Quelle: OSM-Knoten'];

    /** Voreinstellung der Auswahl. @return array{k: string[], sep: string, bem: string[]} */
    public static function defaultOptions(): array
    {
        return ['k' => ['ref', '', ''], 'sep' => 'strich', 'bem' => ['note', 'src']];
    }

    private const TYPE_MAP = ['underground' => 'unterflur', 'pillar' => 'ueberflur', 'pipe' => 'saugbrunnen'];
    private const POSITION_MAP = ['lane' => 'strasse', 'sidewalk' => 'gehweg', 'green' => 'gruenstreifen', 'parking_lot' => 'parkplatz'];

    public const MAX_NODES = 20000;

    /** Abfrage für Overpass Turbo: alle Hydranten im Gebiet des Ortsnamens. */
    public static function turboQuery(string $place): string
    {
        return "[out:json][timeout:60];\n{{geocodeArea:" . $place . "}}->.a;\n(\n  node[\"emergency\"=\"fire_hydrant\"](area.a);\n);\nout body;";
    }

    public static function turboUrl(string $place): string
    {
        $place = trim(str_replace(['{', '}', "\n", "\r"], ' ', $place));
        return $place === '' ? 'https://overpass-turbo.eu/' : 'https://overpass-turbo.eu/?Q=' . rawurlencode(self::turboQuery($place)) . '&R';
    }
    /**
     * Liest Hydranten-Knoten aus Text: Overpass-JSON, GeoJSON (Overpass Turbo) oder OSM-XML. Andere Objekte als Hydranten werden ignoriert.
     * @return array<int, array<string, mixed>>
     * @throws RuntimeException wenn der Text nicht lesbar ist
     */
    public static function parseNodes(string $text): array
    {
        $text = trim(preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text);
        $raw = [];
        if ($text !== '' && $text[0] === '{') {
            $d = json_decode($text, true);
            if (!is_array($d)) {
                throw new RuntimeException('Die Daten sind kein gültiges JSON.');
            }
            if (isset($d['elements']) && is_array($d['elements'])) {
                $raw = $d['elements'];
            } elseif (($d['type'] ?? '') === 'FeatureCollection' && is_array($d['features'] ?? null)) {
                foreach ($d['features'] as $f) {
                    $g = $f['geometry'] ?? null;
                    if (!is_array($g) || ($g['type'] ?? '') !== 'Point' || !isset($g['coordinates'][0], $g['coordinates'][1])) {
                        continue;
                    }
                    $props = is_array($f['properties'] ?? null) ? $f['properties'] : [];
                    $id = (string) ($props['@id'] ?? $f['id'] ?? '');
                    if (preg_match('#^node/(\d+)$#', $id, $m) !== 1) {
                        continue;
                    }
                    $tags = [];
                    foreach ($props as $k => $v) {
                        if (is_string($k) && $k !== '' && $k[0] !== '@' && is_scalar($v)) {
                            $tags[$k] = (string) $v;
                        }
                    }
                    $raw[] = ['type' => 'node', 'id' => (int) $m[1], 'lat' => (float) $g['coordinates'][1], 'lon' => (float) $g['coordinates'][0], 'tags' => $tags];
                }
            } else {
                throw new RuntimeException('Das JSON enthält keine OpenStreetMap-Daten (weder „elements“ noch GeoJSON).');
            }
        } elseif ($text !== '' && $text[0] === '<') {
            $prev = libxml_use_internal_errors(true);
            $xml = simplexml_load_string($text, 'SimpleXMLElement', LIBXML_NONET);
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
            if ($xml === false) {
                throw new RuntimeException('Die Datei ist kein gültiges OSM-XML.');
            }
            foreach ($xml->node as $n) {
                $tags = [];
                foreach ($n->tag as $t) {
                    $tags[(string) $t['k']] = (string) $t['v'];
                }
                $raw[] = ['type' => 'node', 'id' => (int) $n['id'], 'lat' => (float) $n['lat'], 'lon' => (float) $n['lon'], 'tags' => $tags];
            }
        } else {
            throw new RuntimeException('Die Daten sind weder JSON noch OSM-XML.');
        }
        $out = [];
        foreach ($raw as $e) {
            if (!is_array($e) || ($e['type'] ?? '') !== 'node' || !isset($e['id'], $e['lat'], $e['lon']) || !is_numeric($e['lat']) || !is_numeric($e['lon'])) {
                continue;
            }
            $tags = is_array($e['tags'] ?? null) ? $e['tags'] : [];
            if (!in_array($tags['emergency'] ?? '', ['fire_hydrant'], true)) {
                continue;
            }
            if (abs((float) $e['lat']) > 90 || abs((float) $e['lon']) > 180) {
                continue;
            }
            $out[(int) $e['id']] = ['type' => 'node', 'id' => (int) $e['id'], 'lat' => (float) $e['lat'], 'lon' => (float) $e['lon'], 'tags' => array_map('strval', array_filter($tags, 'is_scalar'))];
            if (count($out) > self::MAX_NODES) {
                throw new RuntimeException('Die Daten enthalten mehr als ' . self::MAX_NODES . ' Hydranten. Bitte ein kleineres Gebiet wählen.');
            }
        }
        return array_values($out);
    }

    /**
     * Durchfluss aus einem OSM-Wert („1600“, „1600 l/min“, „26,7 l/s“, „96 m³/h“, „<400“) als Zahl in l/min, ein „<“ oder „>“ bleibt erhalten.
     * Bei mehreren Zahlen („800-1600“, „800;1600“) gilt die erste. Leer, wenn nichts Brauchbares erkannt wird.
     */
    public static function parseFlow(string $v): string
    {
        $v = trim($v);
        $t = preg_replace('/^[^\d]*/u', '', $v) ?? '';
        // erste Zahl samt Einheit dahinter
        if ($t === '' || preg_match('/^(\d{1,3}(?:[.,]\d{3})+(?!\d)|\d+(?:[.,]\d+)?)\s*(.*)$/su', $t, $m) !== 1) {
            return '';
        }
        $num = $m[1];
        $unit = mb_strtolower($m[2]);
        // „1.600“ / „1,600“ = Tausendertrennzeichen, „26,7“ = Dezimalzahl
        $n = preg_match('/^\d{1,3}(?:[.,]\d{3})+$/', $num) === 1 ? (float) str_replace([',', '.'], '', $num) : (float) str_replace(',', '.', $num);
        if (preg_match('~^(m3|m³|m\^3|cbm|qm)\s*/\s*(h|std)~u', $unit) === 1) {
            $n = $n * 1000 / 60;
        } elseif (preg_match('~^(l|ltr|liter)\s*/\s*h~u', $unit) === 1) {
            $n = $n / 60;
        } elseif (preg_match('~^(l|ltr|liter)\s*/\s*s|^lps|^ls\b~u', $unit) === 1) {
            $n = $n * 60;
        } elseif (preg_match('~^gpm|^gal~u', $unit) === 1) {
            $n = $n * 3.78541;
        }
        $n = (int) round($n);
        if ($n < 1 || $n > 999999) {
            return '';
        }
        $sign = '';
        if (preg_match('/^\s*(<|≤|&lt;)/u', $v) === 1) {
            $sign = '<';
        } elseif (preg_match('/^\s*(>|≥|&gt;)/u', $v) === 1) {
            $sign = '>';
        }
        return $sign . $n;
    }

    /** Druck aus einem OSM-Wert („4“, „4,5 bar“, „400 kPa“, „0.4 MPa“, „60 psi“) in bar, mit Komma; leer, wenn nichts Brauchbares erkannt wird. */
    public static function parsePressure(string $v): string
    {
        $v = trim($v);
        if ($v === '' || preg_match('/(\d+(?:[.,]\d+)?)\s*(.*)$/su', preg_replace('/^[^\d]*/u', '', $v) ?? '', $m) !== 1) {
            return '';
        }
        $n = (float) str_replace(',', '.', $m[1]);
        $unit = mb_strtolower(trim($m[2]));
        if (str_starts_with($unit, 'mbar')) {
            $n /= 1000;
        } elseif (str_starts_with($unit, 'kpa')) {
            $n /= 100;
        } elseif (str_starts_with($unit, 'mpa')) {
            $n *= 10;
        } elseif (str_starts_with($unit, 'psi')) {
            $n *= 0.0689476;
        } elseif (str_starts_with($unit, 'atm')) {
            $n *= 1.01325;
        }
        $n = round($n, 2);
        if ($n <= 0 || $n > 99) {
            return '';
        }
        return rtrim(rtrim(number_format($n, 2, ',', ''), '0'), ',');
    }

    /** Entfernung in Metern. */
    public static function distance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 6371000.0;
        $p1 = deg2rad($lat1);
        $p2 = deg2rad($lat2);
        $dp = $p2 - $p1;
        $dl = deg2rad($lon2 - $lon1);
        $a = sin($dp / 2) ** 2 + cos($p1) * cos($p2) * sin($dl / 2) ** 2;
        return 2 * $r * asin(min(1.0, sqrt($a)));
    }

    private static function clean(mixed $v): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $v));
    }

    /**
     * Wandelt OSM-Knoten in Zeilen im Importformat um.
     * @param array<int, array<string, mixed>> $nodes
     * @param array{k?: string[], sep?: string, bem?: string[]} $opts Auswahl der Felder für Kennung (in Reihenfolge), Trennzeichen und Bemerkung
     * @return array{rows: array<int, string[]>, near: int, unknown_type: int}
     */
    public static function toRows(array $nodes, string $defaultType, bool $skipNear, array $opts = []): array
    {
        $opts += self::defaultOptions();
        $fields = array_values(array_filter(array_unique((array) $opts['k']), static fn ($f) => isset(self::KENNUNG_FIELDS[$f])));
        $sep = self::SEPARATORS[(string) $opts['sep']] ?? self::SEPARATORS['strich'];
        $bemFields = array_values(array_filter((array) $opts['bem'], static fn ($f) => isset(self::NOTE_FIELDS[$f])));
        $existing = [];
        if ($skipNear) {
            foreach (Db::all('SELECT lat, lon FROM {hydrants} WHERE lat IS NOT NULL AND lon IS NOT NULL') as $h) {
                $existing[] = [(float) $h['lat'], (float) $h['lon']];
            }
        }
        $used = [];
        foreach (Db::all('SELECT kennung FROM {hydrants}') as $h) {
            $used[Csv::compareKey((string) $h['kennung'])] = true;
        }
        usort($nodes, static fn ($a, $b) => (int) $a['id'] <=> (int) $b['id']);
        $rows = [];
        $near = 0;
        $unknown = 0;
        foreach ($nodes as $n) {
            $lat = (float) $n['lat'];
            $lon = (float) $n['lon'];
            $tags = is_array($n['tags'] ?? null) ? $n['tags'] : [];
            if ($skipNear) {
                foreach ($existing as [$la, $lo]) {
                    if (abs($la - $lat) < 0.0005 && abs($lo - $lon) < 0.001 && self::distance($lat, $lon, $la, $lo) <= self::NEAR_METERS) {
                        $near++;
                        continue 2;
                    }
                }
            }
            // Typ
            $mapped = self::TYPE_MAP[(string) ($tags['fire_hydrant:type'] ?? '')] ?? '';
            $typ = $mapped !== '' && HydrantType::exists($mapped) ? $mapped : '';
            if ($typ === '') {
                $typ = $defaultType;
                $unknown++;
            }
            // Durchmesser (mm, erste Zahl)
            $dn = '';
            if (preg_match('/\d{2,4}/', (string) ($tags['fire_hydrant:diameter'] ?? ''), $m) === 1 && (int) $m[0] >= 1 && (int) $m[0] <= 2000) {
                $dn = (string) (int) $m[0];
            }
            // Position
            $pos = self::POSITION_MAP[(string) ($tags['fire_hydrant:position'] ?? '')] ?? '';
            $pos = $pos !== '' && HydrantPosition::exists($pos) ? HydrantPosition::label($pos) : '';
            // Kennung aus den gewählten Feldern; leere Felder entfallen, sind alle leer, gilt die OSM-ID
            $ref = self::clean($tags['ref'] ?? '');
            $loc = self::clean($tags['fire_hydrant:location'] ?? '');
            $nodeText = 'OSM ' . $n['id'];
            $values = ['ref' => $ref, 'location' => $loc, 'node' => $nodeText];
            $parts = [];
            foreach ($fields as $f) {
                if ($values[$f] !== '') {
                    $parts[] = $values[$f];
                }
            }
            $kennung = $parts ? implode($sep, $parts) : $nodeText;
            if (mb_strlen($kennung) > 190) {
                $kennung = rtrim(mb_substr($kennung, 0, 190));
            }
            // Eindeutig machen: erst mit OSM-ID, dann mit Zähler
            if (isset($used[Csv::compareKey($kennung)])) {
                $base = mb_substr($kennung, 0, 170);
                $kennung = (in_array('node', $fields, true) || $kennung === $nodeText) ? $base : $base . $sep . $nodeText;
                for ($i = 2; isset($used[Csv::compareKey($kennung)]); $i++) {
                    $kennung = mb_substr($base, 0, 160) . ' (' . $i . ')';
                }
            }
            $used[Csv::compareKey($kennung)] = true;
            // Bemerkung
            $lines = [];
            foreach (['ref' => ['Referenz', $ref], 'location' => ['Lage', $loc], 'note' => ['Notiz', self::clean($tags['note'] ?? $tags['description'] ?? '')]] as $key => [$label, $val]) {
                if (in_array($key, $bemFields, true) && $val !== '') {
                    $lines[] = $label . ': ' . $val;
                }
            }
            if (in_array('src', $bemFields, true)) {
                $lines[] = 'Quelle: OpenStreetMap, Knoten ' . $n['id'];
            }
            $rows[] = [
                $kennung, HydrantType::label($typ), $dn, self::parseFlow((string) ($tags['flow_rate'] ?? '')), self::parsePressure((string) ($tags['fire_hydrant:pressure'] ?? '')), $pos, '',
                number_format($lat, 7, '.', ''), number_format($lon, 7, '.', ''), implode("\n", $lines), 'ja',
            ];
        }
        return ['rows' => $rows, 'near' => $near, 'unknown_type' => $unknown];
    }

    public static function csv(array $rows): string
    {
        return Csv::write(HydrantImport::COLUMNS, $rows);
    }
}
