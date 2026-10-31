<?php
declare(strict_types=1);

/** Export von Hydranten als OpenStreetMap-Knoten (CSV mit OSM-Schlüsseln oder OSM-XML für JOSM). */
final class OsmExport
{
    /** Spalten der CSV in dieser Reihenfolge (OSM-Schlüssel, dazu lat und lon). */
    public const COLUMNS = [
        'emergency', 'fire_hydrant:diameter', 'fire_hydrant:location', 'fire_hydrant:position',
        'fire_hydrant:type', 'flow_rate', 'fire_hydrant:pressure', 'ref', 'lat', 'lon',
    ];

    /** Woher Werte für ref und fire_hydrant:location kommen: Schlüssel => Anzeige ('' = nicht schreiben) */
    public const SOURCES = ['' => 'nicht schreiben', 'kennung' => 'Kennung', 'bemerkung' => 'Bemerkung'];

    /** OSM-Typ „pipe“ = Saugbrunnen: bekommt zusätzlich fire_hydrant:pressure=suction */
    public const SUCTION_OSM_TYPE = 'pipe';

    /** Höchstlänge eines Tag-Werts in OSM */
    public const MAX_VALUE = 255;

    /** Zulässige Optionen aus einem Formular. @return array{ref:string,loc:string} */
    public static function options(array $src): array
    {
        $get = static fn (string $k, string $default): string => is_string($src[$k] ?? null) ? trim($src[$k]) : $default;
        $ref = $get('ref', 'kennung');
        $loc = $get('loc', '');
        return [
            'ref'  => isset(self::SOURCES[$ref]) ? $ref : 'kennung',
            'loc'  => isset(self::SOURCES[$loc]) ? $loc : '',
        ];
    }

    /** Hydranten der Liste (nach Filter), natürlich nach Kennung sortiert. @return array<int, array<string, mixed>> */
    public static function hydrants(array $filter): array
    {
        [$where, $params] = HydrantFilter::where($filter);
        $rows = Db::all('SELECT h.* FROM {hydrants} h' . $where, $params);
        usort($rows, static fn (array $a, array $b): int => strnatcasecmp((string) $a['kennung'], (string) $b['kennung']));
        return $rows;
    }

    /**
     * Knoten und Zahlen für Hinweise berechnen.
     * @param array<int, array<string, mixed>> $hydrants
     * @return array{nodes: array<int, array{tags: array<string,string>, lat: string, lon: string}>, total:int, noCoords:int, signs:int, truncated:int, noTypeValue:array<string,int>, noPositionValue:array<string,int>}
     */
    public static function build(array $hydrants, array $opt): array
    {
        $res = ['nodes' => [], 'total' => count($hydrants), 'noCoords' => 0, 'signs' => 0, 'truncated' => 0, 'noTypeValue' => [], 'noPositionValue' => []];
        $types = HydrantType::all();
        $positions = HydrantPosition::all();
        foreach ($hydrants as $h) {
            if ($h['lat'] === null || $h['lon'] === null || $h['lat'] === '' || $h['lon'] === '') {
                $res['noCoords']++;
                continue;
            }
            $typ = (string) $h['typ'];
            $tags = ['emergency' => 'fire_hydrant'];
            if ($h['durchmesser_dn'] !== null && $h['durchmesser_dn'] !== '') {
                $tags['fire_hydrant:diameter'] = (string) (int) $h['durchmesser_dn'];
            }
            $tags['fire_hydrant:location'] = self::source($h, $opt['loc'], $res);
            $pos = (string) ($h['position_art'] ?? '');
            if ($pos !== '') {
                $v = (string) ($positions[$pos]['osm_wert'] ?? '');
                if ($v === '') {
                    $label = (string) ($positions[$pos]['name'] ?? $pos);
                    $res['noPositionValue'][$label] = ($res['noPositionValue'][$label] ?? 0) + 1;
                }
                $tags['fire_hydrant:position'] = $v;
            }
            $v = (string) ($types[$typ]['osm_wert'] ?? '');
            if ($v === '') {
                $label = (string) ($types[$typ]['name'] ?? $typ);
                $res['noTypeValue'][$label] = ($res['noTypeValue'][$label] ?? 0) + 1;
            }
            $tags['fire_hydrant:type'] = $v;
            if ($h['durchfluss_lmin'] !== null && $h['durchfluss_lmin'] !== '') {
                $tags['flow_rate'] = (string) (int) $h['durchfluss_lmin'];
                if ((string) ($h['durchfluss_zeichen'] ?? '') !== '') {
                    $res['signs']++;
                }
            }
            if ($v === self::SUCTION_OSM_TYPE) {
                // Saugbrunnen (OSM: fire_hydrant:type=pipe) haben keinen Überdruck, sondern saugen an
                $tags['fire_hydrant:pressure'] = 'suction';
            } elseif ($h['druck_bar'] !== null && $h['druck_bar'] !== '') {
                $tags['fire_hydrant:pressure'] = rtrim(rtrim(number_format((float) $h['druck_bar'], 2, '.', ''), '0'), '.');
            }
            $tags['ref'] = self::source($h, $opt['ref'], $res);
            $res['nodes'][] = [
                'tags' => array_filter($tags, static fn (string $v): bool => $v !== ''),
                'lat'  => Hydrant::coord($h['lat']),
                'lon'  => Hydrant::coord($h['lon']),
            ];
        }
        ksort($res['noTypeValue']);
        ksort($res['noPositionValue']);
        return $res;
    }

    /** Wert aus Kennung oder Bemerkung als einzeiliger Tag-Wert (höchstens 255 Zeichen). */
    private static function source(array $h, string $which, array &$res): string
    {
        if ($which === '') {
            return '';
        }
        $v = (string) ($which === 'kennung' ? $h['kennung'] : ($h['bemerkung'] ?? ''));
        $v = trim((string) preg_replace('/\s*[\r\n]+\s*/', '; ', trim($v)));
        $v = trim((string) preg_replace('/[\t ]+/', ' ', $v));
        if (mb_strlen($v) > self::MAX_VALUE) {
            $v = mb_substr($v, 0, self::MAX_VALUE);
            $res['truncated']++;
        }
        return $v;
    }

    /** CSV (Semikolon, UTF-8 mit BOM wie der übrige Export). */
    public static function csv(array $nodes): string
    {
        $rows = [];
        foreach ($nodes as $n) {
            $row = [];
            foreach (self::COLUMNS as $c) {
                $row[] = $c === 'lat' ? $n['lat'] : ($c === 'lon' ? $n['lon'] : ($n['tags'][$c] ?? ''));
            }
            $rows[] = $row;
        }
        return Csv::write(self::COLUMNS, $rows);
    }

    /** OSM-XML für JOSM: neue Knoten mit negativen Nummern. */
    public static function xml(array $nodes): string
    {
        $esc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<osm version="0.6" generator="Hydrantenverwaltung">' . "\n";
        $id = 0;
        foreach ($nodes as $n) {
            $id--;
            $out .= '  <node id="' . $id . '" lat="' . $esc($n['lat']) . '" lon="' . $esc($n['lon']) . '" visible="true">' . "\n";
            foreach ($n['tags'] as $k => $v) {
                $out .= '    <tag k="' . $esc($k) . '" v="' . $esc($v) . '"/>' . "\n";
            }
            $out .= "  </node>\n";
        }
        return $out . "</osm>\n";
    }
}
