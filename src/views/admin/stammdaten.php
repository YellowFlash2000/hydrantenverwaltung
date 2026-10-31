<?php
/** @var array $types */
/** @var array $typeUse */
/** @var string[] $typeErrors */
/** @var array $positions */
/** @var array $posUse */
/** @var string[] $posErrors */
/** @var array $defects */
/** @var string[] $defectErrors */
$errs = static function (array $list): void {
    if ($list) { ?>
  <div class="alert alert-error" role="alert"><?php foreach ($list as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
<?php }
};
$osmSelect = static function (string $name, array $values, string $current, string $label): string {
    $h = '<select name="' . e($name) . '" aria-label="' . e($label) . '"><option value="">– nicht exportieren –</option>';
    foreach ($values as $v => $text) {
        $h .= '<option value="' . e($v) . '"' . ($current === $v ? ' selected' : '') . '>' . e($text) . '</option>';
    }
    return $h . '</select>';
};
?>
<h1>Stammdaten</h1>
<p class="hint">Hier pflegen Sie die Auswahllisten der Anwendung. Sprungmarken:
  <a href="#typen">Typen</a> · <a href="#positionen">Positionen</a> · <a href="#maengel">Mängelkatalog</a></p>

<section class="card" id="typen">
  <h2>Hydrantentypen</h2>
  <p class="hint">Jeder Typ hat ein Symbol, mit dem er auf der Karte und in der Hydrantenliste erscheint. Typen, die Hydranten verwenden, lassen sich nicht löschen, nur sperren: Gesperrte Typen sind bei neuen Hydranten nicht mehr wählbar, bestehende behalten sie. Der <strong>OSM-Wert</strong> ist die Übersetzung für <code>fire_hydrant:type</code> beim Export für OpenStreetMap; ohne Wert wird der Typ dort nicht angegeben.</p>
  <?php $errs($typeErrors); ?>
  <form method="post" action="<?= e(url('admin/stammdaten/typen')) ?>">
    <?= csrf_field() ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Reihenfolge</th><th>Name</th><th>Symbol</th><th>OSM-Wert</th><th>Hydranten</th><th>Aktiv</th><th>Löschen</th></tr></thead>
        <tbody>
        <?php foreach ($types as $key => $r): $id = (int) $r['id']; $n = (int) ($typeUse[$key] ?? 0); ?>
          <tr class="<?= (int) $r['aktiv'] === 1 ? '' : 'row-inactive' ?>">
            <td data-label="Reihenfolge"><input type="text" inputmode="numeric" name="sort[<?= $id ?>]" value="<?= e((string) $r['sortierung']) ?>" class="input-short" aria-label="Reihenfolge"></td>
            <td data-label="Name"><input type="text" name="name[<?= $id ?>]" value="<?= e((string) $r['name']) ?>" maxlength="60" aria-label="Name des Typs"></td>
            <td data-label="Symbol">
              <div class="symbol-picker" role="radiogroup" aria-label="Symbol für <?= e((string) $r['name']) ?>">
                <?php foreach (HydrantType::SYMBOLS as $sk => $sl): ?>
                  <label title="<?= e($sl) ?>"><input type="radio" name="symbol[<?= $id ?>]" value="<?= e($sk) ?>" <?= $r['symbol'] === $sk ? 'checked' : '' ?>> <?= HydrantType::svg($sk, 18) ?><span class="visually-hidden"><?= e($sl) ?></span></label>
                <?php endforeach; ?>
              </div>
            </td>
            <td data-label="OSM-Wert"><?= $osmSelect('osm[' . $id . ']', HydrantType::OSM_VALUES, (string) ($r['osm_wert'] ?? ''), 'OSM-Wert für ' . $r['name']) ?></td>
            <td data-label="Hydranten"><?= $n ?></td>
            <td data-label="Aktiv"><input type="checkbox" name="aktiv[<?= $id ?>]" value="1" <?= (int) $r['aktiv'] === 1 ? 'checked' : '' ?> aria-label="Aktiv"></td>
            <td data-label="Löschen"><?php if ($n === 0): ?><input type="checkbox" name="loeschen[<?= $id ?>]" value="1" aria-label="Typ <?= e((string) $r['name']) ?> löschen"><?php else: ?><span class="hint">in Benutzung</span><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <h3>Neuen Typ hinzufügen</h3>
    <div class="form-group">
      <label for="typ_neu_name">Name</label>
      <input type="text" id="typ_neu_name" name="neu_name" maxlength="60" autocomplete="off" placeholder="z. B. Wandhydrant">
    </div>
    <div class="symbol-picker" role="radiogroup" aria-label="Symbol für den neuen Typ">
      <?php foreach (HydrantType::SYMBOLS as $sk => $sl): ?>
        <label title="<?= e($sl) ?>"><input type="radio" name="neu_symbol" value="<?= e($sk) ?>" <?= $sk === 'kreis' ? 'checked' : '' ?>> <?= HydrantType::svg($sk, 18) ?><span class="visually-hidden"><?= e($sl) ?></span></label>
      <?php endforeach; ?>
    </div>
    <div class="form-group">
      <label for="typ_neu_osm">OSM-Wert</label>
      <?= str_replace('<select ', '<select id="typ_neu_osm" ', $osmSelect('neu_osm', HydrantType::OSM_VALUES, '', 'OSM-Wert für den neuen Typ')) ?>
    </div>
    <p class="hint">Ein neuer Typ gilt zunächst für die ersten vier Mängel des Katalogs. Bitte danach im Mängelkatalog (unten) prüfen, welche Mängel für ihn gelten.</p>
    <p class="actions"><button type="submit" class="btn btn-primary">Typen speichern</button></p>
  </form>
</section>

<section class="card" id="positionen">
  <h2>Positionen</h2>
  <?php if (!Settings::bool('show_position')): ?>
    <p class="alert alert-info">Das Feld „Position“ ist in den Einstellungen derzeit ausgeblendet. Die Liste lässt sich trotzdem pflegen und wird beim OSM-Export weiterhin verwendet.</p>
  <?php endif; ?>
  <p class="hint">Wo der Hydrant liegt (z. B. Gehweg). Wie bei den Typen: Benutzte Positionen lassen sich nur sperren, nicht löschen. Der <strong>OSM-Wert</strong> ist die Übersetzung für <code>fire_hydrant:position</code> beim Export für OpenStreetMap.</p>
  <?php $errs($posErrors); ?>
  <form method="post" action="<?= e(url('admin/stammdaten/positionen')) ?>">
    <?= csrf_field() ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Reihenfolge</th><th>Name</th><th>OSM-Wert</th><th>Hydranten</th><th>Aktiv</th><th>Löschen</th></tr></thead>
        <tbody>
        <?php foreach ($positions as $key => $r): $id = (int) $r['id']; $n = (int) ($posUse[$key] ?? 0); ?>
          <tr class="<?= (int) $r['aktiv'] === 1 ? '' : 'row-inactive' ?>">
            <td data-label="Reihenfolge"><input type="text" inputmode="numeric" name="sort[<?= $id ?>]" value="<?= e((string) $r['sortierung']) ?>" class="input-short" aria-label="Reihenfolge"></td>
            <td data-label="Name"><input type="text" name="name[<?= $id ?>]" value="<?= e((string) $r['name']) ?>" maxlength="60" aria-label="Name der Position"></td>
            <td data-label="OSM-Wert"><?= $osmSelect('osm[' . $id . ']', HydrantPosition::OSM_VALUES, (string) ($r['osm_wert'] ?? ''), 'OSM-Wert für ' . $r['name']) ?></td>
            <td data-label="Hydranten"><?= $n ?></td>
            <td data-label="Aktiv"><input type="checkbox" name="aktiv[<?= $id ?>]" value="1" <?= (int) $r['aktiv'] === 1 ? 'checked' : '' ?> aria-label="Aktiv"></td>
            <td data-label="Löschen"><?php if ($n === 0): ?><input type="checkbox" name="loeschen[<?= $id ?>]" value="1" aria-label="Position <?= e((string) $r['name']) ?> löschen"><?php else: ?><span class="hint">in Benutzung</span><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="form-group">
      <label for="pos_neu_name">Neue Position</label>
      <input type="text" id="pos_neu_name" name="neu_name" maxlength="60" autocomplete="off" placeholder="z. B. Radweg">
    </div>
    <div class="form-group">
      <label for="pos_neu_osm">OSM-Wert der neuen Position</label>
      <?= str_replace('<select ', '<select id="pos_neu_osm" ', $osmSelect('neu_osm', HydrantPosition::OSM_VALUES, '', 'OSM-Wert für die neue Position')) ?>
    </div>
    <p class="actions"><button type="submit" class="btn btn-primary">Positionen speichern</button></p>
  </form>
</section>

<section id="maengel">
  <?php include SRC . '/views/admin/_defects.php'; ?>
</section>
