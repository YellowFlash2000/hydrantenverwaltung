<?php
declare(strict_types=1);

/** Verwaltung des Mängelkatalogs (nur Admin). Mängel werden nie gelöscht, nur ausgeblendet. */
final class DefectController extends Controller
{
    public function index(): void
    {
        redirect('admin/stammdaten#maengel');
    }

    public function save(): void
    {
        $errors = [];
        $names = is_array($_POST['name'] ?? null) ? $_POST['name'] : [];
        $sort = is_array($_POST['sort'] ?? null) ? $_POST['sort'] : [];
        $active = is_array($_POST['aktiv'] ?? null) ? $_POST['aktiv'] : [];
        $types = is_array($_POST['typ'] ?? null) ? $_POST['typ'] : [];
        $existing = [];
        foreach (DefectType::all() as $d) {
            $existing[(int) $d['id']] = $d;
        }

        $updates = [];
        foreach ($existing as $id => $d) {
            $name = is_string($names[$id] ?? null) ? trim($names[$id]) : (string) $d['name'];
            if ($name === '' || mb_strlen($name) > 120) {
                $errors[] = 'Jeder Mangel braucht einen Namen (höchstens 120 Zeichen).';
                continue;
            }
            $selected = is_array($types[$id] ?? null) ? array_values(array_filter($types[$id], static fn ($t) => is_string($t) && HydrantType::exists($t))) : [];
            $isActive = isset($active[$id]);
            if ($selected === [] && $isActive) {
                $errors[] = 'Der Mangel „' . $name . '“ ist aktiv, gilt aber für keinen Typ. Bitte einen Typ wählen oder den Mangel ausblenden.';
                continue;
            }
            $order = is_string($sort[$id] ?? null) && preg_match('/^-?\d{1,5}$/', $sort[$id]) === 1 ? (int) $sort[$id] : (int) $d['sortierung'];
            $updates[$id] = [$name, $order, $isActive ? 1 : 0, implode(',', $selected)];
        }

        $new = $this->post('neu_name');
        $newTypes = is_array($_POST['neu_typ'] ?? null) ? array_values(array_filter($_POST['neu_typ'], static fn ($t) => is_string($t) && HydrantType::exists($t))) : [];
        if ($new !== '') {
            if (mb_strlen($new) > 120) {
                $errors[] = 'Der neue Mangel hat einen zu langen Namen (höchstens 120 Zeichen).';
            } elseif ($newTypes === []) {
                $errors[] = 'Für den neuen Mangel bitte mindestens einen Typ wählen.';
            }
        }

        if ($errors) {
            $defects = DefectType::all();
            foreach ($defects as &$d) {
                $id = (int) $d['id'];
                if (isset($updates[$id])) {
                    [$d['name'], $d['sortierung'], $d['aktiv'], $d['gilt_fuer']] = $updates[$id];
                }
            }
            unset($d);
            (new StammdatenController())->renderPage(['defects' => $defects, 'defectErrors' => array_values(array_unique($errors))], 422);
            return;
        }

        foreach ($updates as $id => [$name, $order, $isActive, $list]) {
            Db::exec('UPDATE {defect_types} SET name = ?, sortierung = ?, aktiv = ?, gilt_fuer = ? WHERE id = ?', [$name, $order, $isActive, $list, $id]);
        }
        if ($new !== '') {
            $max = (int) Db::val('SELECT COALESCE(MAX(sortierung), 0) FROM {defect_types}');
            Db::insert('INSERT INTO {defect_types} (name, sortierung, aktiv, gilt_fuer) VALUES (?, ?, 1, ?)', [$new, $max + 10, implode(',', $newTypes)]);
        }
        Audit::log('maengelkatalog_geaendert', 'defect_types', null, $new !== '' ? 'neu: ' . mb_substr($new, 0, 120) : '');
        flash('success', 'Der Mängelkatalog wurde gespeichert.');
        redirect('admin/stammdaten#maengel');
    }
}
