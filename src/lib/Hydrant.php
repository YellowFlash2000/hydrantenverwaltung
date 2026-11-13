<?php
declare(strict_types=1);

/** Stammdaten-Logik für Hydranten: Auswahllisten, Eingabeprüfung, Karten-Links. */
final class Hydrant
{
    public const MAX_IMAGES = 20;

    public static function find(int $id): ?array
    {
        return $id > 0 ? Db::one('SELECT * FROM {hydrants} WHERE id = ?', [$id]) : null;
    }

    /** Dezimalzahl mit Punkt oder Komma einlesen. */
    public static function parseDecimal(string $v): ?float
    {
        $v = str_replace(',', '.', trim($v));
        return preg_match('/^-?\d+(\.\d+)?$/', $v) === 1 ? (float) $v : null;
    }

    /** Koordinate für Anzeige und Formularfelder (ohne überflüssige Nullen). */
    public static function coord(mixed $v): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        $s = number_format((float) $v, 7, '.', '');
        return rtrim(rtrim($s, '0'), '.');
    }

    public static function number(mixed $v, int $decimals = 0): string
    {
        return $v === null || $v === '' ? '–' : number_format((float) $v, $decimals, ',', '.');
    }

    /** Durchfluss für die Anzeige, z. B. „3.200 l/min“ oder „< 400 l/min“. */
    public static function flow(array $h): string
    {
        if (($h['durchfluss_lmin'] ?? null) === null || $h['durchfluss_lmin'] === '') {
            return '–';
        }
        $z = (string) ($h['durchfluss_zeichen'] ?? '');
        return ($z !== '' ? $z . ' ' : '') . self::number($h['durchfluss_lmin']) . ' l/min';
    }

    public static function typLabel(?string $t): string
    {
        return HydrantType::label($t);
    }

    public static function lageLabel(?string $l): string
    {
        return HydrantPosition::label($l);
    }

    // ---- Karten-Links ---------------------------------------------------------

    public static function googleMapsUrl(mixed $lat, mixed $lon): string
    {
        return 'https://www.google.com/maps/search/?api=1&query=' . self::coord($lat) . ',' . self::coord($lon);
    }

    /**
     * Adresse auf openfiremap.org. Format laut Vorgabe: https://openfiremap.org/#17/51.12345/10.12345/osmde
     * (Zoomstufe/Breite/Länge/Kartenebene). Das Format ist hier an einer einzigen Stelle festgelegt.
     */
    public static function openFireMapUrl(mixed $lat, mixed $lon): string
    {
        return 'https://openfiremap.org/#18/' . self::coord($lat) . '/' . self::coord($lon) . '/osmde';
    }

    // ---- Eingabeprüfung -------------------------------------------------------

    /**
     * Liest das Hydranten-Formular.
     * @param array|null $existing bestehender Datensatz (beim Bearbeiten), für nicht angezeigte Felder
     * @return array{0: array<string, mixed>, 1: string[]} [Werte, Fehlermeldungen]
     */
    public static function readForm(array $post, ?array $existing, bool $forceAll = false): array
    {
        $errors = [];
        $str = static fn (string $k): string => is_string($post[$k] ?? null) ? trim($post[$k]) : '';

        $kennung = $str('kennung');
        if ($kennung === '' || mb_strlen($kennung) > 190) {
            $errors[] = 'Bitte eine Kennung angeben (höchstens 190 Zeichen).';
        }

        $typ = $str('typ');
        if (!isset(HydrantType::choices((string) ($existing['typ'] ?? null))[$typ])) {
            $errors[] = 'Bitte einen Typ wählen.';
        }

        // Position, Durchfluss und Druck nur auswerten, wenn das Feld in den Einstellungen aktiv ist
        // (oder beim CSV-Import); sonst bleibt der gespeicherte Wert unverändert.
        $useLage = $forceAll || Settings::bool('show_position');
        $useFlow = $forceAll || Settings::bool('show_flow');
        $usePressure = $forceAll || Settings::bool('show_pressure');

        $lage = $useLage ? $str('position_art') : (string) ($existing['position_art'] ?? '');
        if ($useLage && $lage !== '' && !isset(HydrantPosition::choices((string) ($existing['position_art'] ?? null))[$lage])) {
            $errors[] = 'Ungültige Position.';
        }

        $dn = null;
        if ($str('durchmesser_dn') !== '') {
            if (preg_match('/^\d{1,4}$/', $str('durchmesser_dn')) !== 1 || (int) $str('durchmesser_dn') < 1 || (int) $str('durchmesser_dn') > 2000) {
                $errors[] = 'Durchmesser: Bitte die Nennweite als ganze Zahl angeben (z. B. 100).';
            } else {
                $dn = (int) $str('durchmesser_dn');
            }
        }

        // Durchfluss: Zahl in l/min, optional mit "<" oder ">" (auch direkt im Zahlenfeld eingegeben, z. B. "<400")
        $flow = isset($existing['durchfluss_lmin']) ? (int) $existing['durchfluss_lmin'] : null;
        $flowSign = (string) ($existing['durchfluss_zeichen'] ?? '');
        if ($useFlow) {
            $flow = null;
            $flowSign = $str('durchfluss_zeichen');
            $flowIn = $str('durchfluss_lmin');
            if (preg_match('/^([<>])\s*(.*)$/', $flowIn, $fm) === 1) {
                $flowSign = $fm[1];
                $flowIn = trim($fm[2]);
            }
            if (!in_array($flowSign, ['', '<', '>'], true)) {
                $errors[] = 'Durchfluss: Ungültiges Vergleichszeichen.';
                $flowSign = '';
            }
            if ($flowIn !== '') {
                if (preg_match('/^\d{1,6}$/', $flowIn) !== 1) {
                    $errors[] = 'Durchfluss: Bitte eine ganze Zahl in l/min angeben (z. B. 1600, auch „<400“ ist möglich).';
                } else {
                    $flow = (int) $flowIn;
                }
            } elseif ($flowSign !== '') {
                $errors[] = 'Durchfluss: Zu „' . $flowSign . '“ fehlt die Zahl.';
            }
        }

        $pressure = $existing['druck_bar'] ?? null;
        if ($usePressure) {
            $pressure = null;
            if ($str('druck_bar') !== '') {
                $p = self::parseDecimal($str('druck_bar'));
                if ($p === null || $p < 0 || $p > 99) {
                    $errors[] = 'Druck: Bitte einen Wert in bar angeben (z. B. 4,5).';
                } else {
                    $pressure = $p;
                }
            }
        }

        $lat = $lon = null;
        $latIn = $str('lat');
        $lonIn = $str('lon');
        if ($latIn !== '' || $lonIn !== '') {
            $la = self::parseDecimal($latIn);
            $lo = self::parseDecimal($lonIn);
            if ($latIn === '' || $lonIn === '') {
                $errors[] = 'Koordinaten: Bitte Breite und Länge angeben oder beide Felder leer lassen.';
            } elseif ($la === null || $lo === null || $la < -90 || $la > 90 || $lo < -180 || $lo > 180) {
                $errors[] = 'Koordinaten: Bitte Breite (-90 bis 90) und Länge (-180 bis 180) als Dezimalzahl angeben, z. B. 54,1712739.';
            } else {
                $lat = $la;
                $lon = $lo;
            }
        }

        $note = $str('bemerkung');
        if (mb_strlen($note) > 2000) {
            $errors[] = 'Die Bemerkung ist zu lang (höchstens 2000 Zeichen).';
        }

        if ($kennung !== '' && Db::val('SELECT COUNT(*) FROM {hydrants} WHERE kennung = ? AND id <> ?', [$kennung, (int) ($existing['id'] ?? 0)]) > 0) {
            $errors[] = 'Die Kennung „' . $kennung . '“ ist bereits vergeben.';
        }

        return [[
            'kennung'         => $kennung,
            'typ'             => $typ,
            'durchmesser_dn'  => $dn,
            'durchfluss_lmin' => $flow,
            'durchfluss_zeichen' => $flow === null || $flowSign === '' ? null : $flowSign,
            'druck_bar'       => $pressure,
            'position_art'    => $lage === '' ? null : $lage,
            'lat'             => $lat,
            'lon'             => $lon,
            'bemerkung'       => $note === '' ? null : $note,
        ], $errors];
    }

    /** Eingaben unverändert (nur getrimmt) für die erneute Anzeige des Formulars nach einem Fehler. */
    public static function rawForm(array $post): array
    {
        $str = static fn (string $k): string => is_string($post[$k] ?? null) ? trim($post[$k]) : '';
        $out = [];
        foreach (['kennung', 'typ', 'durchmesser_dn', 'durchfluss_lmin', 'durchfluss_zeichen', 'druck_bar', 'position_art', 'lat', 'lon', 'bemerkung', 'tour_id'] as $k) {
            $out[$k] = $str($k);
        }
        return $out;
    }

    /** Gespeicherte Werte in die Schreibweise der Formularfelder bringen. */
    public static function formValues(array $h): array
    {
        return [
            'kennung'         => (string) $h['kennung'],
            'typ'             => (string) $h['typ'],
            'durchmesser_dn'  => $h['durchmesser_dn'] === null ? '' : (string) $h['durchmesser_dn'],
            'durchfluss_lmin' => $h['durchfluss_lmin'] === null ? '' : (string) $h['durchfluss_lmin'],
            'durchfluss_zeichen' => (string) ($h['durchfluss_zeichen'] ?? ''),
            'druck_bar'       => $h['druck_bar'] === null || $h['druck_bar'] === '' ? '' : rtrim(rtrim(number_format((float) $h['druck_bar'], 2, ',', ''), '0'), ','),
            'position_art'    => (string) ($h['position_art'] ?? ''),
            'lat'             => self::coord($h['lat'] ?? null),
            'lon'             => self::coord($h['lon'] ?? null),
            'bemerkung'       => (string) ($h['bemerkung'] ?? ''),
        ];
    }

    /** Werte für die Speicherung in DECIMAL-Spalten aufbereiten. */
    private static function dbValues(array $v): array
    {
        return [
            $v['kennung'], $v['typ'], $v['durchmesser_dn'], $v['durchfluss_lmin'], $v['durchfluss_zeichen'],
            $v['druck_bar'] === null ? null : number_format((float) $v['druck_bar'], 2, '.', ''),
            $v['position_art'],
            $v['lat'] === null ? null : number_format((float) $v['lat'], 7, '.', ''),
            $v['lon'] === null ? null : number_format((float) $v['lon'], 7, '.', ''),
            $v['bemerkung'],
        ];
    }

    public static function insert(array $v, int $aktiv = 1): int
    {
        $now = now();
        return Db::insert(
            'INSERT INTO {hydrants} (kennung, typ, durchmesser_dn, durchfluss_lmin, durchfluss_zeichen, druck_bar, position_art, lat, lon, bemerkung, aktiv, created_at, updated_at)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [...self::dbValues($v), $aktiv, $now, $now]
        );
    }

    public static function update(int $id, array $v): void
    {
        Db::exec(
            'UPDATE {hydrants} SET kennung = ?, typ = ?, durchmesser_dn = ?, durchfluss_lmin = ?, durchfluss_zeichen = ?, druck_bar = ?, position_art = ?, lat = ?, lon = ?, bemerkung = ?, updated_at = ? WHERE id = ?',
            [...self::dbValues($v), now(), $id]
        );
    }

    /** Lesbare Liste der Änderungen für das Protokoll. */
    public static function diff(array $old, array $new): string
    {
        $labels = [
            'kennung' => 'Kennung', 'typ' => 'Typ', 'durchmesser_dn' => 'DN', 'durchfluss_lmin' => 'Durchfluss',
            'druck_bar' => 'Druck', 'position_art' => 'Position', 'lat' => 'Breite', 'lon' => 'Länge', 'bemerkung' => 'Bemerkung',
        ];
        $norm = static function (string $k, mixed $v): string {
            if ($v === null || $v === '') {
                return '–';
            }
            return match ($k) {
                'lat', 'lon' => self::coord($v),
                'druck_bar'  => number_format((float) $v, 2, '.', ''),
                default      => (string) $v,
            };
        };
        foreach (['old', 'new'] as $which) {
            $row = $which === 'old' ? $old : $new;
            $combined = ($row['durchfluss_lmin'] ?? null) === null ? null : ((string) ($row['durchfluss_zeichen'] ?? '') . $row['durchfluss_lmin']);
            if ($which === 'old') {
                $old['durchfluss_lmin'] = $combined;
            } else {
                $new['durchfluss_lmin'] = $combined;
            }
        }
        $out = [];
        foreach ($labels as $k => $label) {
            $a = $norm($k, $old[$k] ?? null);
            $b = $norm($k, $new[$k] ?? null);
            if ($a !== $b) {
                $out[] = $label . ': ' . mb_substr($a, 0, 60) . ' → ' . mb_substr($b, 0, 60);
            }
        }
        return implode('; ', $out);
    }

    // ---- Kartenpunkte ---------------------------------------------------------

    /** Ein Kartenpunkt im Format, das map.js erwartet. */
    public static function mapPoint(array $r): array
    {
        return [
            'id'     => (int) $r['id'],
            'name'   => (string) $r['kennung'],
            'typ'    => (string) $r['typ'],
            'symbol' => HydrantType::symbolOf((string) $r['typ']),
            'info'   => trim(self::typLabel($r['typ']) . ($r['durchmesser_dn'] !== null ? ' · DN ' . $r['durchmesser_dn'] : '')),
            'lat'    => (float) self::coord($r['lat']),
            'lon'    => (float) self::coord($r['lon']),
            'active' => (int) $r['aktiv'] === 1,
            'url'    => url('hydranten/' . (int) $r['id']),
        ];
    }

    /**
     * Alle aktiven Hydranten mit Koordinaten als Hintergrund für Detail- und Auswahlkarte.
     * @return array<int, array<string, mixed>>
     */
    public static function otherPoints(?int $excludeId = null): array
    {
        $rows = Db::all(
            'SELECT id, kennung, typ, durchmesser_dn, lat, lon, aktiv FROM {hydrants} WHERE aktiv = 1 AND lat IS NOT NULL AND lon IS NOT NULL AND id <> ?',
            [(int) $excludeId]
        );
        return array_map([self::class, 'mapPoint'], $rows);
    }

    // ---- Bilder ---------------------------------------------------------------

    /**
     * Bilder eines Hydranten, die der Betrachter sehen darf (siehe HydrantImages::visible).
     * @return array<int, array<string, mixed>>
     */
    public static function images(int $hydrantId, bool $all = false, ?int $userId = null, ?int $outingId = null): array
    {
        return HydrantImages::visible($hydrantId, $all, $userId, $outingId);
    }
}
