<?php
/** @var array $h */
/** @var array $images */
/** @var ?array $campaign */
/** @var bool $inCampaign */
/** @var ?array $tour */
/** @var ?array $current */
/** @var array $history */
/** @var array $changeLog */
/** @var array $defectList */
/** @var array $revForm */
/** @var string[] $revErrors */
$canEdit = Auth::hasRole('editor');
$active = (int) $h['aktiv'] === 1;
$hasCoords = $h['lat'] !== null && $h['lon'] !== null;
$lat = Hydrant::coord($h['lat']);
$lon = Hydrant::coord($h['lon']);
$pressure = Settings::bool('show_pressure');
$showFlow = Settings::bool('show_flow');
$showPos = Settings::bool('show_position');
?>
<div class="card-head">
  <div>
    <p class="crumb"><a href="<?= e(url('hydranten')) ?>">← Alle Hydranten</a></p>
    <h1><?= e($h['kennung']) ?>
      <?php if (!$active): ?><span class="badge badge-off">deaktiviert</span><?php endif; ?>
    </h1>
  </div>
  <?php if ($canEdit): ?>
    <div class="actions actions-top">
      <a class="btn btn-primary" href="<?= e(url('hydranten/' . (int) $h['id'] . '/bearbeiten')) ?>">Bearbeiten</a>
      <form method="post" action="<?= e(url('hydranten/' . (int) $h['id'] . '/aktiv')) ?>" data-confirm="<?= $active ? 'Diesen Hydranten wirklich deaktivieren? Die bisherigen Revisionen bleiben erhalten.' : 'Diesen Hydranten wieder aktivieren?' ?>">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-secondary"><?= $active ? 'Deaktivieren' : 'Wieder aktivieren' ?></button>
      </form>
    </div>
  <?php endif; ?>
</div>

<div class="detail-grid">
  <div class="card">
    <h2>Stammdaten</h2>
    <dl class="facts">
      <dt>Typ</dt><dd><?= e(Hydrant::typLabel($h['typ'])) ?></dd>
      <?php if ($showPos): ?><dt>Position</dt><dd><?= e(Hydrant::lageLabel($h['position_art'])) ?></dd><?php endif; ?>
      <dt>Durchmesser</dt><dd><?= $h['durchmesser_dn'] === null ? '–' : 'DN ' . e((string) $h['durchmesser_dn']) ?></dd>
      <?php if ($showFlow): ?><dt>Durchfluss</dt><dd<?= ($h['durchfluss_zeichen'] ?? '') === '<' ? ' class="flow-warn"' : '' ?>><?= e(Hydrant::flow($h)) ?><?= ($h['durchfluss_zeichen'] ?? '') === '<' ? ' <span class="badge badge-warn">liefert weniger</span>' : '' ?></dd><?php endif; ?>
      <?php if ($pressure): ?>
        <dt>Druck</dt><dd><?= $h['druck_bar'] === null ? '–' : e(Hydrant::number($h['druck_bar'], 1)) . ' bar' ?></dd>
      <?php endif; ?>
      <dt>Koordinaten</dt>
      <dd>
        <?php if ($hasCoords): ?>
          <span id="coords"><?= e($lat) ?>, <?= e($lon) ?></span>
        <?php else: ?>–<?php endif; ?>
      </dd>
      <?php if ($tour !== null): ?>
        <dt>Tour</dt><dd><?= $canEdit ? '<a href="' . e(url('touren/' . (int) $tour['id'])) . '">' . e($tour['name']) . '</a>' : e($tour['name']) ?></dd>
      <?php endif; ?>
      <dt>Bemerkung</dt><dd><?= ($h['bemerkung'] ?? '') === '' ? '–' : nl2br(e((string) $h['bemerkung'])) ?></dd>
    </dl>
    <p class="hint">Angelegt am <?= e(format_dt($h['created_at'])) ?> · zuletzt geändert am <?= e(format_dt($h['updated_at'])) ?></p>
  </div>

  <div class="card">
    <h2>Standort</h2>
    <?php if ($hasCoords): ?>
      <div class="map map-detail js-map" data-mode="detail" data-symbol="<?= e(HydrantType::symbolOf((string) $h['typ'])) ?>" data-lat="<?= e($lat) ?>" data-lon="<?= e($lon) ?>" data-others="<?= e((string) json_encode($others, JSON_UNESCAPED_UNICODE)) ?>" tabindex="0" aria-label="Karte mit dem Standort des Hydranten"></div>
      <div class="actions">
        <a class="btn btn-secondary" href="<?= e(Hydrant::googleMapsUrl($h['lat'], $h['lon'])) ?>" target="_blank" rel="noopener noreferrer">Google Maps</a>
        <a class="btn btn-secondary" href="<?= e(Hydrant::openFireMapUrl($h['lat'], $h['lon'])) ?>" target="_blank" rel="noopener noreferrer">OpenFireMap</a>
      </div>
    <?php else: ?>
      <p class="hint">Für diesen Hydranten sind keine Koordinaten hinterlegt.</p>
      <?php if ($canEdit): ?><p><a href="<?= e(url('hydranten/' . (int) $h['id'] . '/bearbeiten')) ?>">Koordinaten ergänzen</a></p><?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php
  $suggest = Auth::hasRole('reviser') && !Auth::hasRole('editor');
  $isEditor = Auth::hasRole('editor');
  $imgPrefix = 'bild/';
  $suggestAction = 'hydranten/' . (int) $h['id'] . '/bilder-vorschlag';
  $outdatedBase = 'hydranten/' . (int) $h['id'] . '/bilder/';
  include SRC . '/views/images/_block.php';
?>

<div class="card" id="pruefung">
  <h2>Prüfung</h2>
  <?php if ($campaign !== null && !$inCampaign): ?>
    <div class="alert alert-warning" role="note">
      <?php if ($tour === null): ?>Dieser Hydrant gehört zu keiner Tour.<?php else: ?>Die Tour „<?= e($tour['name']) ?>“ dieses Hydranten gehört nicht zum aktiven Prüfdurchgang „<?= e($campaign['name']) ?>“.<?php endif; ?>
      Eine hier erfasste Revision wird gespeichert und steht im Verlauf, zählt aber für keinen Prüfdurchgang und nicht im Fortschritt der Touren. Ein Mangel steht in der Mängelübersicht unter „Ohne Prüfdurchgang“, bis der Hydrant wieder als in Ordnung geprüft wird.
    </div>
  <?php elseif ($campaign !== null): ?>
    <p>
      Prüfdurchgang <strong><?= e($campaign['name']) ?></strong>:
      <?php if ($current === null): ?><span class="badge">offen</span>
      <?php elseif ($current['ergebnis'] === Revision::DEFECT): ?><span class="badge badge-warn">Mangel</span> festgestellt am <?= e(format_dt($current['revised_at'])) ?>
      <?php else: ?><span class="badge badge-ok">in Ordnung</span> geprüft am <?= e(format_dt($current['revised_at'])) ?><?php endif; ?>
    </p>
  <?php else: ?>
    <div class="alert alert-warning" role="note">
      Zurzeit ist kein Prüfdurchgang aktiv. Eine hier erfasste Revision wird gespeichert und steht im Verlauf, zählt aber für keinen Prüfdurchgang und nicht im Fortschritt der Touren. Ein Mangel steht in der Mängelübersicht unter „Ohne Prüfdurchgang“, bis der Hydrant wieder als in Ordnung geprüft wird.
    </div>
  <?php endif; ?>

  <?php if (Auth::hasRole('reviser') && $active): ?>
    <h3><?= $current === null ? 'Revision eintragen' : 'Erneut prüfen (die letzte Prüfung zählt)' ?></h3>
    <?php
      $hydrant = $h;
      $revAction = url('hydranten/' . (int) $h['id'] . '/revision');
      $revButton = 'Revision speichern';
      include SRC . '/views/revisions/_form.php';
    ?>
  <?php elseif (Auth::hasRole('reviser') && !$active): ?>
    <p class="hint">Deaktivierte Hydranten werden nicht geprüft.</p>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Verlauf der Revisionen</h2>
  <?php if (!$history): ?>
    <p class="hint">Es wurde noch keine Revision erfasst.</p>
  <?php else: ?>
    <ul class="history">
      <?php foreach ($history as $r): ?>
        <li>
          <strong><?= e(format_dt($r['revised_at'])) ?></strong>
          <?= $r['ergebnis'] === Revision::DEFECT ? '<span class="badge badge-warn">Mangel</span>' : '<span class="badge badge-ok">in Ordnung</span>' ?>
          <span class="meta">
            <?= $r['campaign_name'] !== null ? ' · ' . e($r['campaign_name']) : '' ?>
            <?= $r['tour_name'] !== null ? ' · Tour ' . e($r['tour_name']) : '' ?>
            · <?= $r['quelle'] === 'tour' ? 'Tour-Link' : 'Anwendung' ?>
            <?php if (($r['teilnehmer'] ?? '') !== ''): ?> · Teilnehmer: <?= e((string) $r['teilnehmer']) ?><?php elseif (($r['user_name'] ?? '') !== ''): ?> · <?= e((string) $r['user_name']) ?><?php endif; ?>
          </span>
          <?php if ($r['defects']): ?>
            <ul class="defect-list"><?php foreach ($r['defects'] as $name): ?><li><?= e($name) ?></li><?php endforeach; ?></ul>
          <?php endif; ?>
          <?php if (($r['bemerkung'] ?? '') !== ''): ?><div><?= nl2br(e((string) $r['bemerkung'])) ?></div><?php endif; ?>
          <?php if ($r['images']): ?>
            <div class="photos">
              <?php foreach ($r['images'] as $im): ?>
                <a href="<?= e(url('revbild/' . (int) $im['id'] . '/full')) ?>" data-lightbox data-caption="Mangel vom <?= e(format_dt($r['revised_at'])) ?>"><img src="<?= e(url('revbild/' . (int) $im['id'] . '/thumb')) ?>" alt="Foto zum Mangel" loading="lazy"></a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<?php if (Auth::hasRole('admin')): ?>
<div class="card" id="aenderungsverlauf">
  <h2>Änderungsverlauf</h2>
  <?php if (!$changeLog): ?>
    <p class="hint">Keine Einträge im Protokoll.</p>
  <?php else: ?>
    <ul class="change-log">
      <?php foreach ($changeLog as $c): ?>
        <li>
          <strong><?= e(date('d.m.Y H:i', (int) strtotime((string) $c['created_at']))) ?></strong>
          · <?= e(AuditLog::label((string) $c['action'])) ?>
          <span class="meta">· <?= e((string) $c['user_label']) ?></span>
          <?php if (($c['details'] ?? '') !== ''): ?><div class="hint"><?= nl2br(e((string) $c['details'])) ?></div><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <p class="hint"><a href="<?= e(url('admin/protokoll', ['objekt' => 'hydrant:' . (int) $h['id']])) ?>">Vollständiges Protokoll zu diesem Hydranten</a></p>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php include SRC . '/views/images/_lightbox.php'; ?>
