<?php
/** @var array $form */
/** @var string[] $errors */
/** @var ?array $result */
/** @var array $types */
?>
<p class="back"><a href="<?= e(url('admin/import')) ?>">← Import und Export</a></p>
<h1>Hydranten aus OpenStreetMap</h1>
<p class="hint">Übernimmt alle Hydranten (<code>emergency=fire_hydrant</code>) einer Gemeinde aus OpenStreetMap als Grundlage. Daraus entsteht eine CSV-Datei im Importformat; vor dem Import sehen Sie wie immer eine Vorschau. Die Daten stammen von den <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap-Mitwirkenden</a> (Lizenz ODbL) und sind nicht überall vollständig.</p>

<?php if ($errors): ?>
  <div class="alert alert-error" role="alert"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<?php if ($result !== null): ?>
  <div class="card">
    <h2>Ergebnis für „<?= e($result['area']) ?>“</h2>
    <div class="dashboard-stats">
      <div class="stat"><strong><?= (int) $result['found'] ?></strong><span>bei OSM gefunden</span></div>
      <div class="stat"><strong><?= count($result['rows']) ?></strong><span>zum Import</span></div>
      <?php if ($result['near'] > 0): ?><div class="stat"><strong><?= (int) $result['near'] ?></strong><span>schon vorhanden (≤ <?= (int) Osm::NEAR_METERS ?> m)</span></div><?php endif; ?>
      <?php if ($result['unknown'] > 0): ?><div class="stat"><strong><?= (int) $result['unknown'] ?></strong><span>ohne Typangabe</span></div><?php endif; ?>
    </div>
    <?php if ($result['unknown'] > 0): ?><p class="hint">Hydranten ohne Typangabe bei OSM bekommen den Typ „<?= e(HydrantType::label($form['typ'])) ?>“.</p><?php endif; ?>
    <?php if (!$result['rows']): ?>
      <p>Es gibt nichts Neues zu importieren.</p>
    <?php else: ?>
      <p class="hint">Die Kennung lässt sich nach dem Import in jedem Hydranten ändern.</p>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Kennung</th><th>Typ</th><th>DN</th><th>Durchfluss</th><th>Druck</th><th>Position</th><th>Koordinaten</th><th>Bemerkung</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($result['rows'], 0, 15) as $r): ?>
          <tr><td data-label="Kennung"><?= e($r[0]) ?></td><td data-label="Typ"><?= e($r[1]) ?></td><td data-label="DN"><?= e($r[2]) ?></td><td data-label="Durchfluss"><?= e($r[3]) ?></td><td data-label="Druck"><?= e($r[4]) ?></td><td data-label="Position"><?= e($r[5]) ?></td><td data-label="Koordinaten"><?= e($r[7] . ', ' . $r[8]) ?></td><td data-label="Bemerkung"><?= nl2br(e($r[9])) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php if (count($result['rows']) > 15): ?><p class="hint">… und <?= count($result['rows']) - 15 ?> weitere.</p><?php endif; ?>
      <form method="post" action="<?= e(url('admin/import/hydranten/ausfuehren')) ?>" class="actions">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($result['token']) ?>">
        <input type="hidden" name="default" value="ignore">
        <input type="hidden" name="aktion" value="vorschau">
        <button type="submit" class="btn btn-primary">Zur Importvorschau</button>
        <a class="btn btn-secondary" href="<?= e(url('admin/osm/csv/' . $result['token'])) ?>">CSV herunterladen</a>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="card">
  <h2>Datei aus Overpass Turbo einlesen</h2>
  <form method="post" id="osm-form" enctype="multipart/form-data" action="<?= e(url('admin/osm/datei')) ?>">
    <?= csrf_field() ?>
    <ol class="steps">
      <li>
        <div class="form-group">
          <label for="gemeinde">Name der Gemeinde</label>
          <input type="text" id="gemeinde" name="gemeinde" value="<?= e($form['gemeinde']) ?>" maxlength="100" placeholder="z. B. Musterstadt">
          <p class="hint">Bei gleichnamigen Orten genauer angeben, z. B. „Musterstadt, Musterkreis“.</p>
        </div>
      </li>
      <li>
        <p><a class="btn btn-secondary" id="turbo-link" href="<?= e(Osm::turboUrl($form['gemeinde'])) ?>" target="_blank" rel="noopener noreferrer">Abfrage in Overpass Turbo öffnen</a></p>
        <p class="hint">Dort oben auf „Ausführen“ klicken, dann „Export“ → „Daten“ → „als GeoJSON herunterladen“. (Auch „Rohdaten“ als JSON und OSM-XML werden gelesen.)</p>
      </li>
      <li>
        <div class="form-group">
          <label for="datei">Heruntergeladene Datei</label>
          <input type="file" id="datei" name="datei" required accept=".json,.geojson,.osm,.xml,application/json">
        </div>
      </li>
    </ol>
    <div class="form-group">
      <label for="typ">Typ für Hydranten ohne Typangabe bei OSM</label>
      <select id="typ" name="typ">
        <?php foreach ($types as $k => $label): ?><option value="<?= e($k) ?>" <?= $form['typ'] === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
      </select>
      <p class="hint">Bei OSM sind „underground“ (Unterflur) und „pillar“ (Überflur) und „pipe“ (Saugbrunnen) vorgesehen. Alles andere bekommt diesen Typ.</p>
    </div>
    <fieldset class="form-group">
      <legend>Kennung</legend>
      <p class="hint">Woraus soll die Kennung der Hydranten bestehen? Die Felder werden in dieser Reihenfolge aneinandergehängt. Ist ein Feld bei einem Hydranten leer, entfällt es; sind alle leer, gilt „OSM <em>Nummer</em>“. Doppelte Kennungen werden eindeutig gemacht.</p>
      <?php foreach ([1, 2, 3] as $i): ?>
        <div class="form-group">
          <label for="k<?= $i ?>"><?= $i ?>. Feld</label>
          <select id="k<?= $i ?>" name="k<?= $i ?>">
            <option value="">– nicht verwenden –</option>
            <?php foreach (Osm::KENNUNG_FIELDS as $key => $label): ?><option value="<?= e($key) ?>" <?= ($form['k'][$i - 1] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
          </select>
        </div>
      <?php endforeach; ?>
      <div class="form-group">
        <label for="sep">Trennzeichen</label>
        <select id="sep" name="sep">
          <?php foreach (Osm::SEPARATOR_LABELS as $key => $label): ?><option value="<?= e($key) ?>" <?= $form['sep'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
      </div>
    </fieldset>
    <fieldset class="form-group">
      <legend>In die Bemerkung schreiben</legend>
      <p class="hint">Jede gewählte Angabe steht in einer eigenen Zeile mit Bezeichnung, z. B. „Lage: Musterstraße 1“. Leere Angaben entfallen.</p>
      <?php foreach (Osm::NOTE_FIELDS as $key => $label): ?>
        <label class="check"><input type="checkbox" name="bem[]" value="<?= e($key) ?>" <?= in_array($key, $form['bem'], true) ? 'checked' : '' ?>> <?= e($label) ?></label>
      <?php endforeach; ?>
    </fieldset>
    <label class="check"><input type="checkbox" name="near" value="1" <?= $form['near'] === '1' ? 'checked' : '' ?>> Hydranten überspringen, die weniger als <?= (int) Osm::NEAR_METERS ?> m von einem vorhandenen Hydranten entfernt sind</label>
    <p class="actions"><button type="submit" class="btn btn-primary">Datei einlesen</button></p>
  </form>
</div>
