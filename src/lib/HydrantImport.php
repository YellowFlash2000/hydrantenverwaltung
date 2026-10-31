<?php
declare(strict_types=1);

/** CSV-Import und -Export der Hydranten-Stammdaten. */
final class HydrantImport
{
    /** Spalten der Export-/Mustervorlage in dieser Reihenfolge. */
    public const COLUMNS = ['kennung', 'typ', 'durchmesser_dn', 'durchfluss_lmin', 'druck_bar', 'position', 'tour', 'lat', 'lon', 'bemerkung', 'aktiv'];

    /** Zulässige Spaltenüberschriften (nach Csv::headerKey) je Feld. */
    private const ALIASES = [
        'kennung'         => ['kennung', 'hydrant', 'hydrantenkennung', 'bezeichnung', 'name'],
        'typ'             => ['typ', 'type', 'art'],
        'durchmesser_dn'  => ['durchmesser_dn', 'durchmesser', 'dn', 'nennweite'],
        'durchfluss_lmin' => ['durchfluss_lmin', 'durchfluss', 'durchfluss_l_min'],
        'druck_bar'       => ['druck_bar', 'druck'],
        'position_art'    => ['position', 'position_art', 'lage'],
        'lat'             => ['lat', 'breite', 'latitude'],
        'lon'             => ['lon', 'lng', 'laenge', 'longitude'],
        'bemerkung'       => ['bemerkung', 'bemerkungen', 'beschreibung', 'notiz'],
        'tour'            => ['tour', 'tourname'],
        'aktiv'           => ['aktiv', 'status'],
    ];

    /** Bekannte Spalten, die nicht übernommen werden. */
    private const IGNORED = ['id', 'osm_node', 'osmnode', 'tour_id', 'created_at', 'updated_at', 'angelegt', 'geaendert'];

    public const MODES = ['update' => 'Aktualisieren', 'new' => 'Als neuen Hydranten anlegen', 'ignore' => 'Ignorieren'];

    /** Zwischenspeicher für eine hochgeladene oder erzeugte CSV (gleiche Datei wie in ImportController). */
    public static function tmpFile(string $token): string
    {
        $dir = STORAGE . '/tmp';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir . '/import-csv-' . $token . '.csv';
    }

    // ---- Auswertung und Plan -------------------------------------------------------

    /**
     * Wertet die CSV aus und plant, was passieren würde (schreibt nichts).
     * @param string $text      CSV-Inhalt als UTF-8
     * @param string $default   Vorbelegung bei vorhandener Kennung: update|new|ignore|ask
     * @param array  $decisions Entscheidungen je Zeile: [Zeile => ['mode' => ..., 'newkennung' => ...]]
     * @return array<string, mixed>
     */
    public static function plan(string $text, string $default, array $decisions = []): array
    {
        [$header, $rows] = Csv::parse($text);
        $plan = ['fatal' => null, 'new_tours' => [], 'ignored' => [], 'columns' => [], 'rows' => [], 'counts' => [], 'undecided' => 0, 'problems' => 0];

        // Spalten zuordnen
        $map = [];
        foreach ($header as $i => $h) {
            $key = Csv::headerKey($h);
            if ($key === '') {
                continue;
            }
            $found = null;
            foreach (self::ALIASES as $field => $aliases) {
                if (in_array($key, $aliases, true)) {
                    $found = $field;
                    break;
                }
            }
            if ($found !== null && !in_array($found, $map, true)) {
                $map[$i] = $found;
            } elseif ($found === null) {
                $plan['ignored'][] = trim(Csv::clean($h));
            }
        }
        $plan['columns'] = array_values($map);
        if (!in_array('kennung', $map, true)) {
            $plan['fatal'] = 'In der Datei fehlt die Spalte „kennung“ (alternativ „hydrant“). Bitte die Überschriftenzeile prüfen oder die Mustervorlage verwenden.';
            return $plan;
        }
        if (!in_array('typ', $map, true)) {
            $plan['fatal'] = 'In der Datei fehlt die Spalte „typ“. Bitte die Überschriftenzeile prüfen oder die Mustervorlage verwenden.';
            return $plan;
        }
        if (!$rows) {
            $plan['fatal'] = 'Die Datei enthält keine Datenzeilen.';
            return $plan;
        }

        // 1. Durchgang: Zellen lesen, Kennungen sammeln
        $items = [];
        foreach ($rows as $r) {
            $vals = [];
            foreach ($map as $i => $field) {
                $vals[$field] = Csv::clean($r['cells'][$i] ?? '');
            }
            if (implode('', $vals) === '') {
                continue; // vollständig leere Zeile
            }
            $vals['kennung'] = self::cleanKennung($vals['kennung'] ?? '');
            $items[] = ['line' => $r['line'], 'vals' => $vals];
        }

        $seen = [];      // normalisierte Kennung => Zeile
        $taken = [];     // normalisierte Kennungen, die in dieser Datei schon vergeben sind (inkl. neuer Kennungen)
        foreach ($items as $it) {
            if ($it['vals']['kennung'] !== '') {
                $taken[Csv::compareKey($it['vals']['kennung'])] = true;
            }
        }

        $counts = ['new' => 0, 'update' => 0, 'unchanged' => 0, 'ignore' => 0, 'error' => 0, 'ask' => 0];
        $newKeys = [];
        foreach ($items as $it) {
            $line = (int) $it['line'];
            $vals = $it['vals'];
            $row = ['line' => $line, 'kennung' => $vals['kennung'], 'status' => 'error', 'errors' => [], 'changes' => '',
                    'conflict' => false, 'mode' => '', 'newkennung' => '', 'v' => null, 'aktiv' => null, 'id' => null];

            if ($vals['kennung'] === '') {
                $row['errors'][] = 'Die Kennung fehlt.';
                $plan['rows'][] = $row;
                $counts['error']++;
                continue;
            }
            $key = Csv::compareKey($vals['kennung']);
            if (isset($seen[$key])) {
                $row['errors'][] = 'Die Kennung steht in der Datei mehrfach (schon in Zeile ' . $seen[$key] . ').';
                $plan['rows'][] = $row;
                $counts['error']++;
                continue;
            }
            $seen[$key] = $line;

            $existing = Db::one('SELECT * FROM {hydrants} WHERE kennung = ?', [$vals['kennung']]);
            $row['id'] = $existing === null ? null : (int) $existing['id'];
            $row['conflict'] = $existing !== null;

            // Entscheidung bei vorhandener Kennung
            $mode = 'update';
            $newKennung = '';
            if ($existing !== null) {
                $dec = $decisions[$line] ?? null;
                $mode = is_array($dec) && isset(self::MODES[$dec['mode'] ?? '']) ? (string) $dec['mode'] : ($default === 'ask' ? 'ask' : $default);
                $newKennung = is_array($dec) ? Csv::clean((string) ($dec['newkennung'] ?? '')) : '';
                $row['mode'] = $mode;
                $row['newkennung'] = $newKennung;
                if ($mode === 'ask') {
                    $row['status'] = 'ask';
                    $counts['ask']++;
                    $plan['undecided']++;
                    $plan['rows'][] = $row;
                    continue;
                }
                if ($mode === 'ignore') {
                    $row['status'] = 'ignore';
                    $counts['ignore']++;
                    $plan['rows'][] = $row;
                    continue;
                }
            }

            // Werte zusammenstellen
            $post = $existing !== null && $mode === 'update' ? Hydrant::formValues($existing) : array_fill_keys(['kennung', 'typ', 'durchmesser_dn', 'durchfluss_lmin', 'durchfluss_zeichen', 'druck_bar', 'position_art', 'lat', 'lon', 'bemerkung'], '');
            $errs = [];
            foreach ($vals as $field => $val) {
                if ($field === 'aktiv' || $field === 'tour') {
                    continue;
                }
                $norm = self::normalize($field, $val, $errs);
                $post[$field] = $norm;
                if ($field === 'durchfluss_lmin') {
                    $post['durchfluss_zeichen'] = ''; // das Zeichen steht, falls vorhanden, direkt im Wert ("<400")
                }
            }
            if ($mode === 'new') {
                if ($newKennung === '') {
                    $errs[] = 'Bitte eine neue Kennung eingeben.';
                } else {
                    $nk = Csv::compareKey($newKennung);
                    if (isset($taken[$nk]) || isset($newKeys[$nk]) || Db::val('SELECT COUNT(*) FROM {hydrants} WHERE kennung = ?', [$newKennung]) > 0) {
                        $errs[] = 'Die neue Kennung „' . $newKennung . '“ ist schon vergeben.';
                    } else {
                        $newKeys[$nk] = true;
                    }
                }
                $post['kennung'] = $newKennung;
            }

            $aktiv = null;
            if (array_key_exists('aktiv', $vals) && $vals['aktiv'] !== '') {
                $aktiv = self::parseAktiv($vals['aktiv']);
                if ($aktiv === null) {
                    $errs[] = 'Status „' . $vals['aktiv'] . '“ nicht erkannt (erlaubt: ja/nein, aktiv/deaktiviert, 1/0).';
                }
            }

            // Tour: leere Zelle = Tour entfernen, sonst Tour mit diesem Namen (wird bei Bedarf angelegt)
            $tourChange = null;
            if (array_key_exists('tour', $vals)) {
                $tn = trim((string) preg_replace('/\s+/', ' ', $vals['tour']));
                if ($tn === '') {
                    $tourChange = ['name' => '', 'id' => null];
                } elseif (mb_strlen($tn) > 120) {
                    $errs[] = 'Der Tourname ist zu lang (höchstens 120 Zeichen).';
                } else {
                    $t = Tour::findByName($tn);
                    $tourChange = ['name' => $t !== null ? (string) $t['name'] : $tn, 'id' => $t === null ? null : (int) $t['id']];
                    if ($t === null && !in_array($tn, $plan['new_tours'], true)) {
                        $plan['new_tours'][] = $tn;
                    }
                }
            }

            $existingForCheck = $mode === 'update' ? $existing : null;
            [$v, $formErrs] = Hydrant::readForm($post, $existingForCheck, true);
            $errs = array_merge($errs, $formErrs);
            if ($errs) {
                $row['errors'] = array_values(array_unique($errs));
                $row['status'] = 'error';
                $plan['rows'][] = $row;
                $counts['error']++;
                if ($mode === 'new') {
                    $plan['problems']++;
                }
                continue;
            }
            $row['v'] = $v;
            $row['aktiv'] = $aktiv;
            $row['tour'] = $tourChange;
            if ($existing === null) {
                $row['status'] = 'new';
                $counts['new']++;
            } elseif ($mode === 'new') {
                $row['status'] = 'new';
                $counts['new']++;
            } else {
                $changes = Hydrant::diff($existing, $v);
                if ($tourChange !== null) {
                    $oldId = $existing['tour_id'] === null ? null : (int) $existing['tour_id'];
                    $differs = $tourChange['name'] === '' ? $oldId !== null : ($tourChange['id'] === null || $tourChange['id'] !== $oldId);
                    if ($differs) {
                        $old = $oldId === null ? '–' : (string) (Tour::find($oldId)['name'] ?? '–');
                        $changes .= ($changes === '' ? '' : '; ') . 'Tour: ' . $old . ' → ' . ($tourChange['name'] === '' ? '–' : $tourChange['name']);
                    }
                }
                if ($aktiv !== null && $aktiv !== (int) $existing['aktiv']) {
                    $changes .= ($changes === '' ? '' : '; ') . 'Status: ' . ((int) $existing['aktiv'] === 1 ? 'aktiv' : 'deaktiviert') . ' → ' . ($aktiv === 1 ? 'aktiv' : 'deaktiviert');
                }
                $row['changes'] = $changes;
                $row['status'] = $changes === '' ? 'unchanged' : 'update';
                $counts[$row['status']]++;
            }
            $plan['rows'][] = $row;
        }
        $plan['counts'] = $counts;
        return $plan;
    }

    /** Schreibt das Ergebnis eines Plans in die Datenbank. @return array{new: int, update: int} */
    public static function apply(array $plan): array
    {
        $done = ['new' => 0, 'update' => 0];
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $tourIds = [];
            $tourId = static function (array $change) use (&$tourIds): ?int {
                if ($change['name'] === '') {
                    return null;
                }
                if ($change['id'] !== null) {
                    return (int) $change['id'];
                }
                $found = Tour::findByName($change['name']);
                $tourIds[$change['name']] ??= $found !== null ? (int) $found['id'] : Tour::insert($change['name'], null);
                return $tourIds[$change['name']];
            };
            foreach ($plan['rows'] as $row) {
                if ($row['status'] === 'new') {
                    $newId = Hydrant::insert($row['v'], $row['aktiv'] ?? 1);
                    if (!empty($row['tour'])) {
                        Db::exec('UPDATE {hydrants} SET tour_id = ? WHERE id = ?', [$tourId($row['tour']), $newId]);
                    }
                    $done['new']++;
                } elseif ($row['status'] === 'update') {
                    Hydrant::update((int) $row['id'], $row['v']);
                    if (!empty($row['tour'])) {
                        Db::exec('UPDATE {hydrants} SET tour_id = ? WHERE id = ?', [$tourId($row['tour']), (int) $row['id']]);
                    }
                    if ($row['aktiv'] !== null) {
                        Db::exec('UPDATE {hydrants} SET aktiv = ? WHERE id = ?', [$row['aktiv'], (int) $row['id']]);
                    }
                    $done['update']++;
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return $done;
    }

    // ---- Einzelwerte ------------------------------------------------------------

    /** Kennung bereinigen. Auch ein eingefügter Link „[Text](Adresse)“ wird auf den Text gekürzt. */
    public static function cleanKennung(string $s): string
    {
        if (preg_match('/^\[(.+)\]\([^)]*\)$/s', trim($s), $m) === 1) {
            $s = $m[1];
        }
        return trim((string) preg_replace('/\s+/', ' ', $s));
    }

    /** @param string[] $errs */
    private static function normalize(string $field, string $v, array &$errs): string
    {
        switch ($field) {
            case 'typ':
                if ($v === '') {
                    return '';
                }
                $k = Csv::compareKey($v);
                foreach (HydrantType::labels() as $key => $label) {
                    if ($k === $key || $k === Csv::compareKey($label)) {
                        return $key;
                    }
                }
                $errs[] = 'Unbekannter Typ „' . $v . '“ (erlaubt: ' . implode(', ', HydrantType::labels()) . ').';
                return '';
            case 'position_art':
                $k = Csv::compareKey($v);
                if ($k === '' || in_array($k, ['[leer]', 'leer', '-', '–'], true)) {
                    return '';
                }
                foreach (HydrantPosition::labels() as $key => $label) {
                    if ($k === $key || $k === Csv::compareKey($label)) {
                        return $key;
                    }
                }
                $errs[] = 'Unbekannte Position „' . $v . '“ (erlaubt: ' . implode(', ', HydrantPosition::labels()) . ').';
                return '';
            case 'durchmesser_dn':
                if ($v === '') {
                    return '';
                }
                if (preg_match('/^(?:dn\s*)?(\d{1,4})$/i', $v, $m) === 1) {
                    return $m[1];
                }
                $errs[] = 'Durchmesser „' . $v . '“ ist keine ganze Zahl.';
                return '';
            case 'durchfluss_lmin':
                if ($v === '') {
                    return '';
                }
                if (preg_match('~^([<>])?\s*(\d{1,3}(?:\.\d{3})+|\d+)\s*(?:l\s*/\s*min|l/m)?$~i', $v, $m) === 1) {
                    return $m[1] . str_replace('.', '', $m[2]);
                }
                $errs[] = 'Durchfluss „' . $v . '“ ist keine ganze Zahl in l/min (erlaubt: 1600, <400, >1600).';
                return '';
            case 'druck_bar':
                return trim((string) preg_replace('/\s*bar$/i', '', $v));
            case 'kennung':
                return $v;
            default:
                return $v; // lat, lon, bemerkung: Prüfung in Hydrant::readForm
        }
    }

    private static function parseAktiv(string $v): ?int
    {
        return match (Csv::compareKey($v)) {
            '1', 'ja', 'j', 'true', 'yes', 'aktiv', 'x' => 1,
            '0', 'nein', 'n', 'false', 'no', 'inaktiv', 'deaktiviert', 'gesperrt' => 0,
            default => null,
        };
    }

    // ---- Export und Muster ----------------------------------------------------------

    public static function export(): string
    {
        $out = [];
        $tours = [];
        foreach (Tour::all() as $t) {
            $tours[(int) $t['id']] = (string) $t['name'];
        }
        foreach (Db::all('SELECT * FROM {hydrants}') as $h) {
            $out[] = self::exportRow($h, $h['tour_id'] === null ? '' : ($tours[(int) $h['tour_id']] ?? ''));
        }
        usort($out, static fn ($a, $b) => strnatcasecmp($a[0], $b[0]));
        return Csv::write(self::COLUMNS, $out);
    }

    /** @return string[] */
    private static function exportRow(array $h, string $tour = ''): array
    {
        $f = Hydrant::formValues($h);
        return [
            $f['kennung'],
            Hydrant::typLabel($f['typ']) === '–' ? $f['typ'] : Hydrant::typLabel($f['typ']),
            $f['durchmesser_dn'],
            $f['durchfluss_lmin'] === '' ? '' : $f['durchfluss_zeichen'] . $f['durchfluss_lmin'],
            $f['druck_bar'],
            $f['position_art'] === '' ? '' : Hydrant::lageLabel($f['position_art']),
            $tour,
            $f['lat'],
            $f['lon'],
            $f['bemerkung'],
            (int) $h['aktiv'] === 1 ? 'ja' : 'nein',
        ];
    }

    public static function sample(): string
    {
        return Csv::write(self::COLUMNS, [
            ['Musterstraße 1', 'Unterflur', '100', '1600', '', 'Grünstreifen', 'Tour Nord', '51.1234000', '10.1234000', 'Deckel schwergängig', 'ja'],
            ['Beispielweg 5', 'Unterflur', '100', '<400', '', 'Straße', 'Tour Nord', '51.1238000', '10.1239000', '', 'ja'],
            ['Löschteich Musterstadt', 'Saugbrunnen', '', '', '', '', 'Tour Süd', '51.1250000', '10.1250000', '', 'ja'],
            ['Musterweg 12', 'Überflur', '80', '800', '4,5', 'Gehweg', 'Tour Süd', '51.1242000', '10.1243000', '', 'ja'],
            ['Musterplatz 3', 'Unterflur', '', '', '', '', '', '', '', 'Standort noch einzumessen', 'nein'],
        ]);
    }
}
