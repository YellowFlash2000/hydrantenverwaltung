<?php
/**
 * Block „Bilder“ eines Hydranten mit Galerie. Mit $suggest zusätzlich: neue Bilder einreichen und veraltete melden.
 * @var array $h Hydrant
 * @var array $images sichtbare Bilder
 * @var string $imgPrefix Pfad der Bildadresse bis „…/bild/“
 * @var bool $suggest Einreichen und Melden anbieten
 * @var string $suggestAction Adresse zum Einreichen (nur mit $suggest)
 * @var string $outdatedBase Adresse bis „…/bilder/“, danach {id}/veraltet (nur mit $suggest)
 * @var bool $isEditor Bearbeiter sehen Vorschläge mit Absender und einen Link zur Freigabe
 */
$isEditor = $isEditor ?? false;
$pending = array_filter($images, static fn (array $i): bool => $i['status'] === HydrantImages::PROPOSAL);
$replaceable = array_filter($images, static fn (array $i): bool => $i['status'] === HydrantImages::OK);
?>
<div class="card" id="bilder">
  <h2><?= $suggest ? 'Bilder des Hydranten' : 'Bilder' ?></h2>
  <?php if ($suggest): ?>
    <p class="hint">Neue Fotos werden zuerst von einem Bearbeiter freigegeben. Veraltete Bilder können Sie melden, ein Bearbeiter entscheidet über das Entfernen. Bitte keine Personen oder Kennzeichen fotografieren.</p>
  <?php endif; ?>
  <?php if ($isEditor && $pending): ?>
    <p class="alert alert-info"><?= count($pending) === 1 ? 'Ein Bild wartet' : count($pending) . ' Bilder warten' ?> auf Freigabe. <a href="<?= e(url('hydranten/' . (int) $h['id'] . '/bearbeiten#bilder')) ?>">Zur Freigabe</a></p>
  <?php endif; ?>
  <?php if (!$images): ?>
    <p class="hint">Es sind keine Bilder vorhanden.<?= !$suggest && Auth::hasRole('editor') ? ' Bilder lassen sich beim Bearbeiten hinzufügen.' : '' ?></p>
  <?php else: ?>
    <ul class="gallery">
      <?php foreach ($images as $img):
        $isPending = $img['status'] === HydrantImages::PROPOSAL;
        $outdated = $img['veraltet_at'] !== null;
        $alt = (string) ($img['beschriftung'] ?? 'Bild von ' . $h['kennung']);
        $marker = HydrantImages::markerAttr($img);
        ?>
        <li class="<?= $isPending ? 'is-pending' : '' ?><?= $outdated ? ' is-outdated' : '' ?>">
          <a href="<?= e(url($imgPrefix . (int) $img['id'] . '/full')) ?>" data-lightbox data-caption="<?= e((string) ($img['beschriftung'] ?? '')) ?>"<?= $marker !== '' ? ' data-marker="' . e($marker) . '"' : '' ?>>
            <img src="<?= e(url($imgPrefix . (int) $img['id'] . '/thumb')) ?>" alt="<?= e($alt) ?>" loading="lazy">
          </a>
          <?php if (($img['beschriftung'] ?? '') !== ''): ?><span class="caption"><?= e((string) $img['beschriftung']) ?></span><?php endif; ?>
          <?php if ($isPending): ?>
            <span class="badge badge-warn">wartet auf Freigabe</span>
            <?php if ($isEditor && ($img['vorgeschlagen_von'] ?? '') !== ''): ?><span class="caption">von <?= e((string) $img['vorgeschlagen_von']) ?>, <?= e(format_dt($img['created_at'])) ?></span><?php endif; ?>
          <?php elseif ($outdated): ?>
            <span class="badge badge-off">veraltet gemeldet</span>
            <span class="caption"><?= e((string) ($img['veraltet_von'] ?? '')) ?>, <?= e(format_dt($img['veraltet_at'])) ?></span>
          <?php endif; ?>
          <?php if ($suggest && !$isPending && !$outdated): ?>
            <form method="post" action="<?= e(url($outdatedBase . (int) $img['id'] . '/veraltet')) ?>" data-confirm="Dieses Bild als veraltet melden? Ein Bearbeiter entscheidet, ob es entfernt wird.">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-secondary btn-sm">Als veraltet melden</button>
            </form>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <?php if ($suggest): ?>
    <form method="post" action="<?= e(url($suggestAction)) ?>" enctype="multipart/form-data" class="upload-form">
      <?= csrf_field() ?>
      <h3>Neue Bilder einreichen</h3>
      <div class="form-group">
        <label for="bilder-neu">Bilder auswählen oder aufnehmen</label>
        <input type="file" id="bilder-neu" name="bilder[]" accept="image/jpeg,image/png,image/webp" multiple data-resize data-marker required>
        <p class="hint">Höchstens <?= (int) HydrantImages::MAX_PER_SUGGESTION ?> Bilder auf einmal, <?= (int) Hydrant::MAX_IMAGES ?> je Hydrant. Mit „Hydrant markieren“ setzen Sie ein gelbes Oval auf den Hydranten, damit er im Vollbild auffällt.</p>
      </div>
      <?php if ($replaceable): ?>
        <div class="form-group">
          <label for="ersetzt_id">Das neue Bild ersetzt</label>
          <select id="ersetzt_id" name="ersetzt_id">
            <option value="">– nichts, es ist ein zusätzliches Bild –</option>
            <?php $n = 0; foreach ($replaceable as $img): $n++; ?>
              <option value="<?= (int) $img['id'] ?>">Bild <?= $n ?><?= ($img['beschriftung'] ?? '') !== '' ? ': ' . e((string) $img['beschriftung']) : '' ?></option>
            <?php endforeach; ?>
          </select>
          <p class="hint">Nach der Freigabe wird das neue Bild Titelbild, das ersetzte Bild gilt als veraltet.</p>
        </div>
      <?php endif; ?>
      <button type="submit" class="btn btn-primary">Zur Freigabe einreichen</button>
    </form>
  <?php endif; ?>
</div>
