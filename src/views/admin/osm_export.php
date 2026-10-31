<?php
/** @var array $f */
/** @var array $opt */
/** @var string $desc */
/** @var array $res */
/** @var string $listUrl */
$hidden = HydrantFilter::toQuery($f);
$isPlain = static fn (string $name, string $value): string => ' name="' . e($name) . '" value="' . e($value) . '"';
?>
<p class="back"><a href="<?= e(url($listUrl)) ?>">← Hydrantenliste</a></p>
<h1>Export für OpenStreetMap</h1>
<p class="hint">Erzeugt Hydranten-Knoten mit den Schlüsseln, die OpenStreetMap verwendet (<code>emergency</code>, <code>fire_hydrant:type</code>, <code>fire_hydrant:position</code>, <code>fire_hydrant:diameter</code>, <code>flow_rate</code>, <code>fire_hydrant:pressure</code>, <code>fire_hydrant:location</code>, <code>ref</code>). Angaben, die leer sind, werden weggelassen. Die Datei ist zum Prüfen und Hochladen gedacht; <strong>vor dem Hochladen bitte mit den vorhandenen OSM-Daten abgleichen</strong>, damit keine doppelten Hydranten entstehen.</p>

<div class="card">
  <h2>Was exportiert wird</h2>
  <p>Die Hydranten der aktuellen Hydrantenliste: <strong><?= e($desc) ?></strong>.</p>
  <div class="dashboard-stats">
    <div class="stat"><strong><?= (int) $res['total'] ?></strong><span>in der Liste</span></div>
    <div class="stat"><strong><?= count($res['nodes']) ?></strong><span>mit Koordinaten</span></div>
    <?php if ($res['noCoords'] > 0): ?><div class="stat"><strong><?= (int) $res['noCoords'] ?></strong><span>ohne Koordinaten (werden übersprungen)</span></div><?php endif; ?>
  </div>
  <?php if (!$res['nodes']): ?>
    <div class="alert alert-warning" role="alert">Keiner der Hydranten hat Koordinaten. Für OpenStreetMap sind Koordinaten nötig.</div>
  <?php endif; ?>
  <p class="hint">Anderen Ausschnitt exportieren? Dann in der <a href="<?= e(url($listUrl)) ?>">Hydrantenliste</a> den Filter ändern und dort „Für OpenStreetMap exportieren“ wählen.</p>
</div>

<?php foreach ([
    ['Typen', $res['noTypeValue'], 'fire_hydrant:type'],
    ['Positionen', $res['noPositionValue'], 'fire_hydrant:position'],
] as [$what, $list, $key]): if ($list): ?>
  <div class="alert alert-warning" role="alert">
    Ohne OSM-Wert wird <code><?= e($key) ?></code> nicht geschrieben bei:
    <?php $parts = []; foreach ($list as $label => $n) { $parts[] = e((string) $label) . ' (' . (int) $n . ')'; } echo implode(', ', $parts); ?>.
    Den OSM-Wert tragen Sie unter <a href="<?= e(url('admin/stammdaten#' . ($what === 'Typen' ? 'typen' : 'positionen'))) ?>">Stammdaten</a> ein.
  </div>
<?php endif; endforeach; ?>
<?php if ($res['signs'] > 0): ?>
  <div class="alert alert-info" role="status"><?= (int) $res['signs'] ?> Hydranten haben beim Durchfluss ein Vergleichszeichen (&lt; oder &gt;). OpenStreetMap kennt das nicht; es wird nur die Zahl geschrieben.</div>
<?php endif; ?>

<form method="post" action="<?= e(url('admin/export/osm')) ?>" class="card">
  <?= csrf_field() ?>
  <?php foreach ($hidden as $k => $v): ?><input type="hidden"<?= $isPlain($k, $v) ?>><?php endforeach; ?>
  <h2>Optionen</h2>

  <fieldset class="form-group plain-group">
    <legend>Referenz (<code>ref</code>)</legend>
    <?php foreach (OsmExport::SOURCES as $k => $label): ?>
      <label class="check"><input type="radio" name="ref" value="<?= e($k) ?>" <?= $opt['ref'] === $k ? 'checked' : '' ?>> <span><?= e($label) ?></span></label>
    <?php endforeach; ?>
  </fieldset>

  <fieldset class="form-group plain-group">
    <legend>Lage-Beschreibung (<code>fire_hydrant:location</code>)</legend>
    <?php foreach (OsmExport::SOURCES as $k => $label): ?>
      <label class="check"><input type="radio" name="loc" value="<?= e($k) ?>" <?= $opt['loc'] === $k ? 'checked' : '' ?>> <span><?= e($label) ?></span></label>
    <?php endforeach; ?>
    <p class="hint">Die Kennung enthält oft den Ort (z. B. eine Straße). Mehrzeilige Bemerkungen werden einzeilig mit „;“ getrennt, Werte auf 255 Zeichen gekürzt.</p>
  </fieldset>


  <p class="actions">
    <button type="submit" name="format" value="csv" class="btn btn-primary">Als CSV herunterladen</button>
    <button type="submit" name="format" value="xml" class="btn btn-secondary">Als OSM-XML für JOSM herunterladen</button>
  </p>
  <p class="hint">CSV: Semikolon-getrennt mit den OSM-Schlüsseln als Spaltenköpfen und <code>lat</code>/<code>lon</code>. OSM-XML: neue Knoten, die Sie in JOSM öffnen, prüfen und hochladen können.</p>
</form>
