<?php
declare(strict_types=1);

/** Stammdaten (nur Admin): Hydrantentypen mit Symbol, Positionen und Mängelkatalog auf einer Seite. */
final class StammdatenController extends Controller
{
    public function index(): void
    {
        $this->renderPage();
    }

    /** Seite zeigen. Nach einem Fehler beim Speichern eines Abschnitts mit dessen bisherigen Eingaben und Meldungen. */
    public function renderPage(array $over = [], int $status = 200): void
    {
        $this->render('admin/stammdaten', $over + [
            'title'   => 'Stammdaten',
            'types'   => HydrantType::all(),
            'typeUse' => $this->useCounts('typ'),
            'typeErrors' => [],
            'positions' => HydrantPosition::all(),
            'posUse'  => $this->useCounts('position_art'),
            'posErrors' => [],
            'defects' => DefectType::all(),
            'defectErrors' => [],
        ], $status);
    }

    /** @return array<string, int> Schlüssel => Anzahl Hydranten */
    private function useCounts(string $column): array
    {
        $out = [];
        foreach (Db::all('SELECT ' . $column . ' AS k, COUNT(*) AS n FROM {hydrants} WHERE ' . $column . ' IS NOT NULL GROUP BY ' . $column) as $r) {
            $out[(string) $r['k']] = (int) $r['n'];
        }
        return $out;
    }

    // ---- Typen ----------------------------------------------------------------

    public function saveTypes(): void
    {
        $names = $this->arr('name');
        $symbols = $this->arr('symbol');
        $osms = $this->arr('osm');
        $sort = $this->arr('sort');
        $active = $this->arr('aktiv');
        $delete = $this->arr('loeschen');
        $errors = [];
        $use = $this->useCounts('typ');
        $existing = HydrantType::all();
        $updates = [];
        $deletes = [];
        $seen = [];
        foreach ($existing as $key => $r) {
            $id = (int) $r['id'];
            if (isset($delete[$id])) {
                if (($use[$key] ?? 0) > 0) {
                    $errors[] = 'Der Typ „' . $r['name'] . '“ wird von Hydranten verwendet und kann nicht gelöscht werden. Sperren Sie ihn stattdessen.';
                } else {
                    $deletes[$id] = $key;
                    continue;
                }
            }
            $name = is_string($names[$id] ?? null) ? trim($names[$id]) : (string) $r['name'];
            $symbol = is_string($symbols[$id] ?? null) ? $symbols[$id] : (string) $r['symbol'];
            if ($name === '' || mb_strlen($name) > 60) {
                $errors[] = 'Jeder Typ braucht einen Namen (höchstens 60 Zeichen).';
                continue;
            }
            if (!isset(HydrantType::SYMBOLS[$symbol])) {
                $errors[] = 'Für „' . $name . '“ wurde ein ungültiges Symbol gewählt.';
                continue;
            }
            if (isset($seen[mb_strtolower($name)])) {
                $errors[] = 'Der Name „' . $name . '“ kommt doppelt vor.';
            }
            $seen[mb_strtolower($name)] = true;
            $order = is_string($sort[$id] ?? null) && preg_match('/^-?\d{1,5}$/', $sort[$id]) === 1 ? (int) $sort[$id] : (int) $r['sortierung'];
            $osm = is_string($osms[$id] ?? null) ? $osms[$id] : (string) ($r['osm_wert'] ?? '');
            if ($osm !== '' && !isset(HydrantType::OSM_VALUES[$osm])) {
                $errors[] = 'Für „' . $name . '“ wurde ein ungültiger OSM-Wert gewählt.';
                $osm = '';
            }
            $updates[$id] = [$name, $symbol, $order, isset($active[$id]) ? 1 : 0, $osm];
        }
        $newName = $this->post('neu_name');
        $newSymbol = $this->post('neu_symbol', 'kreis');
        $newOsm = $this->post('neu_osm');
        if ($newName !== '') {
            if (mb_strlen($newName) > 60) {
                $errors[] = 'Der neue Typ hat einen zu langen Namen (höchstens 60 Zeichen).';
            } elseif (isset($seen[mb_strtolower($newName)])) {
                $errors[] = 'Einen Typ „' . $newName . '“ gibt es schon.';
            } elseif (!isset(HydrantType::SYMBOLS[$newSymbol])) {
                $errors[] = 'Für den neuen Typ wurde ein ungültiges Symbol gewählt.';
            } elseif ($newOsm !== '' && !isset(HydrantType::OSM_VALUES[$newOsm])) {
                $errors[] = 'Für den neuen Typ wurde ein ungültiger OSM-Wert gewählt.';
            }
        }
        $remainingActive = count(array_filter($updates, static fn ($u) => $u[3] === 1)) + ($newName !== '' ? 1 : 0);
        if ($remainingActive === 0) {
            $errors[] = 'Mindestens ein Typ muss aktiv bleiben.';
        }
        if ($errors) {
            $types = $existing;
            foreach ($types as $key => &$r) {
                if (isset($updates[(int) $r['id']])) {
                    [$r['name'], $r['symbol'], $r['sortierung'], $r['aktiv'], $r['osm_wert']] = $updates[(int) $r['id']];
                }
            }
            unset($r);
            $this->renderPage(['types' => $types, 'typeErrors' => array_values(array_unique($errors))], 422);
            return;
        }
        foreach ($updates as $id => [$name, $symbol, $order, $isActive, $osm]) {
            Db::exec('UPDATE {hydrant_types} SET name = ?, symbol = ?, osm_wert = ?, sortierung = ?, aktiv = ? WHERE id = ?', [$name, $symbol, $osm, $order, $isActive, $id]);
        }
        foreach ($deletes as $id => $key) {
            Db::exec('DELETE FROM {hydrant_types} WHERE id = ?', [$id]);
            $this->removeTypeFromDefects($key);
        }
        if ($newName !== '') {
            $key = HydrantType::newKey($newName, array_keys($existing));
            $max = (int) Db::val('SELECT COALESCE(MAX(sortierung), 0) FROM {hydrant_types}');
            Db::insert('INSERT INTO {hydrant_types} (schluessel, name, symbol, osm_wert, sortierung, aktiv) VALUES (?, ?, ?, ?, ?, 1)', [$key, $newName, $newSymbol, $newOsm, $max + 10]);
            // Der neue Typ gilt zunächst für die ersten vier Mängel (wie ein Saugbrunnen); der Admin kann das im Mängelkatalog ändern.
            $this->addTypeToDefects($key);
        }
        HydrantType::reset();
        Audit::log('hydranttypen_geaendert', 'hydrant_types', null, ($newName !== '' ? 'neu: ' . mb_substr($newName, 0, 60) : '') . (count($deletes) ? ' gelöscht: ' . count($deletes) : ''));
        flash('success', 'Die Typen wurden gespeichert.');
        if ($newName !== '') {
            flash('warning', 'Bitte im Mängelkatalog prüfen, welche Mängel für den neuen Typ „' . $newName . '“ gelten. Zunächst gelten die ersten vier Mängel der Liste.');
            redirect('admin/stammdaten#maengel');
        }
        redirect('admin/stammdaten#typen');
    }

    private function removeTypeFromDefects(string $key): void
    {
        foreach (Db::all('SELECT id, gilt_fuer FROM {defect_types}') as $d) {
            if (trim((string) $d['gilt_fuer']) === '') {
                continue; // leer = gilt für alle Typen
            }
            $list = array_values(array_filter(array_map('trim', explode(',', (string) $d['gilt_fuer'])), static fn ($x) => $x !== '' && $x !== $key));
            Db::exec('UPDATE {defect_types} SET gilt_fuer = ? WHERE id = ?', [$list ? implode(',', $list) : '-', (int) $d['id']]);
        }
    }

    private function addTypeToDefects(string $key): void
    {
        foreach (Db::all('SELECT id, gilt_fuer FROM {defect_types} ORDER BY sortierung, id LIMIT ' . Installer::DEFECTS_ALL_TYPES) as $d) {
            $list = array_values(array_filter(array_map('trim', explode(',', (string) $d['gilt_fuer'])), static fn ($x) => $x !== ''));
            $list[] = $key;
            Db::exec('UPDATE {defect_types} SET gilt_fuer = ? WHERE id = ?', [implode(',', array_unique($list)), (int) $d['id']]);
        }
    }

    // ---- Positionen -----------------------------------------------------------

    public function savePositions(): void
    {
        $names = $this->arr('name');
        $osms = $this->arr('osm');
        $sort = $this->arr('sort');
        $active = $this->arr('aktiv');
        $delete = $this->arr('loeschen');
        $errors = [];
        $use = $this->useCounts('position_art');
        $existing = HydrantPosition::all();
        $updates = [];
        $deletes = [];
        $seen = [];
        foreach ($existing as $key => $r) {
            $id = (int) $r['id'];
            if (isset($delete[$id])) {
                if (($use[$key] ?? 0) > 0) {
                    $errors[] = 'Die Position „' . $r['name'] . '“ wird von Hydranten verwendet und kann nicht gelöscht werden. Sperren Sie sie stattdessen.';
                } else {
                    $deletes[$id] = $key;
                    continue;
                }
            }
            $name = is_string($names[$id] ?? null) ? trim($names[$id]) : (string) $r['name'];
            if ($name === '' || mb_strlen($name) > 60) {
                $errors[] = 'Jede Position braucht einen Namen (höchstens 60 Zeichen).';
                continue;
            }
            if (isset($seen[mb_strtolower($name)])) {
                $errors[] = 'Der Name „' . $name . '“ kommt doppelt vor.';
            }
            $seen[mb_strtolower($name)] = true;
            $order = is_string($sort[$id] ?? null) && preg_match('/^-?\d{1,5}$/', $sort[$id]) === 1 ? (int) $sort[$id] : (int) $r['sortierung'];
            $osm = is_string($osms[$id] ?? null) ? $osms[$id] : (string) ($r['osm_wert'] ?? '');
            if ($osm !== '' && !isset(HydrantPosition::OSM_VALUES[$osm])) {
                $errors[] = 'Für „' . $name . '“ wurde ein ungültiger OSM-Wert gewählt.';
                $osm = '';
            }
            $updates[$id] = [$name, $order, isset($active[$id]) ? 1 : 0, $osm];
        }
        $newName = $this->post('neu_name');
        $newOsm = $this->post('neu_osm');
        if ($newOsm !== '' && !isset(HydrantPosition::OSM_VALUES[$newOsm])) {
            $errors[] = 'Für die neue Position wurde ein ungültiger OSM-Wert gewählt.';
        }
        if ($newName !== '') {
            if (mb_strlen($newName) > 60) {
                $errors[] = 'Die neue Position hat einen zu langen Namen (höchstens 60 Zeichen).';
            } elseif (isset($seen[mb_strtolower($newName)])) {
                $errors[] = 'Eine Position „' . $newName . '“ gibt es schon.';
            }
        }
        if ($errors) {
            $positions = $existing;
            foreach ($positions as &$r) {
                if (isset($updates[(int) $r['id']])) {
                    [$r['name'], $r['sortierung'], $r['aktiv'], $r['osm_wert']] = $updates[(int) $r['id']];
                }
            }
            unset($r);
            $this->renderPage(['positions' => $positions, 'posErrors' => array_values(array_unique($errors))], 422);
            return;
        }
        foreach ($updates as $id => [$name, $order, $isActive, $osm]) {
            Db::exec('UPDATE {positions} SET name = ?, osm_wert = ?, sortierung = ?, aktiv = ? WHERE id = ?', [$name, $osm, $order, $isActive, $id]);
        }
        foreach ($deletes as $id => $key) {
            Db::exec('DELETE FROM {positions} WHERE id = ?', [$id]);
        }
        if ($newName !== '') {
            $key = HydrantType::newKey($newName, array_keys($existing));
            $max = (int) Db::val('SELECT COALESCE(MAX(sortierung), 0) FROM {positions}');
            Db::insert('INSERT INTO {positions} (schluessel, name, osm_wert, sortierung, aktiv) VALUES (?, ?, ?, ?, 1)', [$key, $newName, $newOsm, $max + 10]);
        }
        HydrantPosition::reset();
        Audit::log('positionen_geaendert', 'positions', null, ($newName !== '' ? 'neu: ' . mb_substr($newName, 0, 60) : '') . (count($deletes) ? ' gelöscht: ' . count($deletes) : ''));
        flash('success', 'Die Positionen wurden gespeichert.');
        redirect('admin/stammdaten#positionen');
    }

    /** @return array<int|string, mixed> */
    private function arr(string $key): array
    {
        return is_array($_POST[$key] ?? null) ? $_POST[$key] : [];
    }
}
