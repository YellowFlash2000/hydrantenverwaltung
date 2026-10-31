<?php
/** @var array $tour */
/** @var array $h */
/** @var ?array $current */
/** @var array $images */
/** @var array $others */
/** @var array $outing */
/** @var array $defectList */
/** @var array $revForm */
/** @var string[] $revErrors */
$base = 't/' . $tour['token'];
$hasCoords = $h['lat'] !== null && $h['lon'] !== null;
$showFlow = Settings::bool('show_flow');
$showPos = Settings::bool('show_position');
$lat = Hydrant::coord($h['lat']);
$lon = Hydrant::coord($h['lon']);
$res = $current['ergebnis'] ?? null;
?>
<p class="back"><a href="<?= e(url($base)) ?>">← Zurück zur Tour „<?= e($tour['name']) ?>“</a></p>
<h1><?= e($h['kennung']) ?></h1>

<div class="card">
  <p>
    <?php if ($res === Revision::DEFECT): ?><span class="badge badge-warn">Mangel</span>
    <?php elseif ($res === Revision::OK): ?><span class="badge badge-ok">in Ordnung</span>
    <?php else: ?><span class="badge">noch nicht geprüft</span><?php endif; ?>
    <?php if ($current !== null): ?>
      <span class="hint"><?= e(format_dt($current['revised_at'])) ?><?= ($current['teilnehmer'] ?? '') !== '' ? ' · ' . e((string) $current['teilnehmer']) : '' ?></span>
    <?php endif; ?>
  </p>
  <dl class="facts">
    <dt>Typ</dt><dd><?= e(Hydrant::typLabel($h['typ'])) ?></dd>
    <?php if ($showPos): ?><dt>Position</dt><dd><?= e(Hydrant::lageLabel($h['position_art'])) ?></dd><?php endif; ?>
    <dt>Durchmesser</dt><dd><?= $h['durchmesser_dn'] === null ? '–' : 'DN ' . e((string) $h['durchmesser_dn']) ?></dd>
    <?php if ($showFlow): ?><dt>Durchfluss</dt><dd<?= ($h['durchfluss_zeichen'] ?? '') === '<' ? ' class="flow-warn"' : '' ?>><?= e(Hydrant::flow($h)) ?></dd><?php endif; ?>
    <?php if (($h['bemerkung'] ?? '') !== ''): ?><dt>Bemerkung</dt><dd><?= nl2br(e((string) $h['bemerkung'])) ?></dd><?php endif; ?>
  </dl>
</div>

<?php if ($hasCoords): ?>
<div class="card">
  <h2>Standort</h2>
  <div class="map map-detail js-map" data-mode="detail" data-symbol="<?= e(HydrantType::symbolOf((string) $h['typ'])) ?>" data-color="<?= e(TourAccess::colorOf($res)) ?>" data-lat="<?= e($lat) ?>" data-lon="<?= e($lon) ?>" data-others="<?= e((string) json_encode($others, JSON_UNESCAPED_UNICODE)) ?>" tabindex="0" aria-label="Karte mit dem Standort des Hydranten"></div>
  <div class="actions">
    <a class="btn btn-secondary" href="<?= e(Hydrant::googleMapsUrl($h['lat'], $h['lon'])) ?>" target="_blank" rel="noopener noreferrer">Google Maps</a>
    <a class="btn btn-secondary" href="<?= e(Hydrant::openFireMapUrl($h['lat'], $h['lon'])) ?>" target="_blank" rel="noopener noreferrer">OpenFireMap</a>
  </div>
</div>
<?php endif; ?>

<?php if ($images): ?>
<div class="card">
  <h2>Bilder</h2>
  <ul class="gallery">
    <?php foreach ($images as $img): ?>
      <li>
        <a href="<?= e(url($base . '/bild/' . (int) $img['id'] . '/full')) ?>" data-lightbox data-caption="<?= e((string) ($img['beschriftung'] ?? '')) ?>">
          <img src="<?= e(url($base . '/bild/' . (int) $img['id'] . '/thumb')) ?>" alt="<?= e((string) ($img['beschriftung'] ?? 'Bild von ' . $h['kennung'])) ?>" loading="lazy">
        </a>
        <?php if (($img['beschriftung'] ?? '') !== ''): ?><span class="caption"><?= e((string) $img['beschriftung']) ?></span><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<div class="card" id="pruefung">
  <h2><?= $current === null ? 'Prüfung eintragen' : 'Erneut prüfen' ?></h2>
  <?php if ($current !== null): ?><p class="hint">Es zählt jeweils die letzte Prüfung dieses Durchgangs.</p><?php endif; ?>
  <?php
    $hydrant = $h;
    $revAction = url($base . '/h/' . (int) $h['id']);
    $revButton = 'Speichern';
    include SRC . '/views/revisions/_form.php';
  ?>
</div>

<dialog id="lightbox" class="lightbox" aria-label="Bildansicht">
  <button type="button" class="lightbox-close" aria-label="Schließen">×</button>
  <img src="" alt="">
  <p class="lightbox-caption"></p>
</dialog>
