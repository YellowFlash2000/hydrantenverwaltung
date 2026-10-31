<?php
/** @var ?array $h */
/** @var array $form */
/** @var string[] $errors */
/** @var array $images */
/** @var ?array $center */
$isNew = $h === null;
$action = $isNew ? url('hydranten/neu') : url('hydranten/' . (int) $h['id'] . '/bearbeiten');
$pressure = Settings::bool('show_pressure');
$showFlow = Settings::bool('show_flow');
$showPos = Settings::bool('show_position');
?>
<div class="card-head">
  <div>
    <p class="crumb"><a href="<?= e($isNew ? url('hydranten') : url('hydranten/' . (int) $h['id'])) ?>">← <?= $isNew ? 'Alle Hydranten' : 'Zurück zum Hydranten' ?></a></p>
    <h1><?= e($title) ?></h1>
  </div>
</div>

<?php if ($errors): ?>
  <div class="alert alert-error" role="alert">
    <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>

<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" id="hydrant-form">
  <?= csrf_field() ?>

  <div class="card">
    <h2>Stammdaten</h2>
    <div class="form-row">
      <div class="form-group grow">
        <label for="kennung">Kennung</label>
        <input type="text" id="kennung" name="kennung" value="<?= e($form['kennung']) ?>" required maxlength="190" <?= $isNew ? 'autofocus' : '' ?> autocomplete="off" placeholder="z. B. Musterstraße 1">
        <p class="hint">Freitext, muss eindeutig sein.</p>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label for="typ">Typ</label>
        <select id="typ" name="typ" required>
          <?php foreach (HydrantType::choices((string) $form['typ']) as $k => $label): ?>
            <option value="<?= e($k) ?>" <?= $form['typ'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($showPos): ?>
      <div class="form-group">
        <label for="position_art">Position</label>
        <select id="position_art" name="position_art">
          <option value="">– keine Angabe –</option>
          <?php foreach (HydrantPosition::choices((string) $form['position_art']) as $k => $label): ?>
            <option value="<?= e($k) ?>" <?= $form['position_art'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
    </div>
    <?php if (!empty($tours)): ?>
    <div class="form-row">
      <div class="form-group">
        <label for="tour_id">Tour</label>
        <select id="tour_id" name="tour_id">
          <option value="">– keine Tour –</option>
          <?php foreach ($tours as $t): ?>
            <option value="<?= (int) $t['id'] ?>" <?= (string) ($form['tour_id'] ?? '') === (string) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <?php endif; ?>
    <div class="form-row">
      <div class="form-group">
        <label for="durchmesser_dn">Durchmesser (DN)</label>
        <input type="text" inputmode="numeric" id="durchmesser_dn" name="durchmesser_dn" value="<?= e($form['durchmesser_dn']) ?>" class="input-short" placeholder="100">
      </div>
      <?php if ($showFlow): ?>
      <div class="form-group">
        <label for="durchfluss_lmin">Durchfluss (l/min)</label>
        <div class="inline-fields">
          <select name="durchfluss_zeichen" id="durchfluss_zeichen" aria-label="Vergleichszeichen" class="input-xshort">
            <option value="" <?= ($form['durchfluss_zeichen'] ?? '') === '' ? 'selected' : '' ?>>genau</option>
            <option value="&lt;" <?= ($form['durchfluss_zeichen'] ?? '') === '<' ? 'selected' : '' ?>>kleiner als (&lt;)</option>
            <option value="&gt;" <?= ($form['durchfluss_zeichen'] ?? '') === '>' ? 'selected' : '' ?>>größer als (&gt;)</option>
          </select>
          <input type="text" inputmode="numeric" id="durchfluss_lmin" name="durchfluss_lmin" value="<?= e($form['durchfluss_lmin']) ?>" class="input-short" placeholder="1600">
        </div>
      </div>
      <?php endif; ?>
      <?php if ($pressure): ?>
        <div class="form-group">
          <label for="druck_bar">Druck (bar)</label>
          <input type="text" inputmode="decimal" id="druck_bar" name="druck_bar" value="<?= e($form['druck_bar']) ?>" class="input-short" placeholder="4,5">
        </div>
      <?php endif; ?>
    </div>
    <div class="form-group">
      <label for="bemerkung">Bemerkung</label>
      <textarea id="bemerkung" name="bemerkung" rows="3" maxlength="2000"><?= e($form['bemerkung']) ?></textarea>
    </div>
  </div>

  <div class="card">
    <h2>Standort</h2>
    <div class="form-row">
      <div class="form-group">
        <label for="lat">Breite (lat)</label>
        <input type="text" inputmode="decimal" id="lat" name="lat" value="<?= e($form['lat']) ?>" placeholder="51.1234567" autocomplete="off">
      </div>
      <div class="form-group">
        <label for="lon">Länge (lon)</label>
        <input type="text" inputmode="decimal" id="lon" name="lon" value="<?= e($form['lon']) ?>" placeholder="10.1234567" autocomplete="off">
      </div>
    </div>
    <div class="map map-picker js-map" id="map" data-mode="picker" data-lat-input="#lat" data-lon-input="#lon"
         data-center="<?= e($center ? (string) json_encode($center) : '') ?>" data-zoom="<?= (int) ($zoom ?? 16) ?>" data-others="<?= e((string) json_encode($others ?? [], JSON_UNESCAPED_UNICODE)) ?>" tabindex="0" aria-label="Karte zur Auswahl des Standorts"></div>
    <div class="actions map-tools">
      <button type="button" class="btn btn-secondary btn-sm" id="btn-locate">Meinen Standort verwenden</button>
      <button type="button" class="btn btn-secondary btn-sm" id="btn-clear">Koordinaten entfernen</button>
    </div>
    <p class="hint" id="locate-status" role="status">Auf die Karte tippen, um den Standort zu setzen. Der Marker lässt sich verschieben. Koordinaten können auch von Hand eingegeben werden (Punkt oder Komma).</p>
  </div>

  <?php if ($isNew): ?>
    <div class="card">
      <h2>Bilder (optional)</h2>
      <div class="form-group">
        <input type="file" name="bilder[]" accept="image/jpeg,image/png,image/webp" multiple data-resize>
        <p class="hint">Mehrere Bilder möglich (höchstens 10 auf einmal). Sie werden vor dem Hochladen verkleinert.</p>
      </div>
    </div>
  <?php endif; ?>

  <div class="actions">
    <button type="submit" class="btn btn-primary"><?= $isNew ? 'Hydrant anlegen' : 'Änderungen speichern' ?></button>
    <a class="btn btn-secondary" href="<?= e($isNew ? url('hydranten') : url('hydranten/' . (int) $h['id'])) ?>">Abbrechen</a>
  </div>
</form>

<?php if (!$isNew): ?>
  <div class="card">
    <h2>Bilder</h2>
    <?php if (!$images): ?>
      <p class="hint">Es sind noch keine Bilder vorhanden.</p>
    <?php else: ?>
      <ul class="image-admin">
        <?php foreach ($images as $i => $img): ?>
          <li>
            <img src="<?= e(url('bild/' . (int) $img['id'] . '/thumb')) ?>" alt="" loading="lazy">
            <div class="image-admin-body">
              <?php if ($i === 0): ?><span class="badge badge-ok">Titelbild</span><?php endif; ?>
              <form method="post" action="<?= e(url('hydranten/' . (int) $h['id'] . '/bilder/' . (int) $img['id'])) ?>" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="aktion" value="beschriftung">
                <label class="sr-only" for="cap<?= (int) $img['id'] ?>">Beschriftung</label>
                <input type="text" id="cap<?= (int) $img['id'] ?>" name="beschriftung" value="<?= e((string) ($img['beschriftung'] ?? '')) ?>" maxlength="255" placeholder="Beschriftung (optional)">
                <button type="submit" class="btn btn-secondary btn-sm">Speichern</button>
              </form>
              <div class="actions">
                <?php if ($i !== 0): ?>
                  <form method="post" action="<?= e(url('hydranten/' . (int) $h['id'] . '/bilder/' . (int) $img['id'])) ?>">
                    <?= csrf_field() ?><input type="hidden" name="aktion" value="titelbild">
                    <button type="submit" class="btn btn-secondary btn-sm">Als Titelbild</button>
                  </form>
                <?php endif; ?>
                <form method="post" action="<?= e(url('hydranten/' . (int) $h['id'] . '/bilder/' . (int) $img['id'])) ?>" data-confirm="Dieses Bild wirklich entfernen?">
                  <?= csrf_field() ?><input type="hidden" name="aktion" value="entfernen">
                  <button type="submit" class="btn btn-danger btn-sm">Entfernen</button>
                </form>
              </div>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <form method="post" action="<?= e(url('hydranten/' . (int) $h['id'] . '/bilder')) ?>" enctype="multipart/form-data" class="upload-form">
      <?= csrf_field() ?>
      <div class="form-group">
        <label for="bilder">Bilder hinzufügen</label>
        <input type="file" id="bilder" name="bilder[]" accept="image/jpeg,image/png,image/webp" multiple data-resize required>
        <p class="hint">JPEG, PNG oder WebP. Bilder werden vor dem Hochladen verkleinert. Höchstens <?= (int) Hydrant::MAX_IMAGES ?> Bilder pro Hydrant.</p>
      </div>
      <button type="submit" class="btn btn-primary">Hochladen</button>
    </form>
  </div>
<?php endif; ?>
