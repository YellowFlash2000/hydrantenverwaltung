<?php
/** @var array $tour */
/** @var array $outing */
/** @var array $hydrants */
/** @var array $points */
/** @var array $covers */
/** @var int $done */
/** @var int $total */
/** @var bool $onlyOpen */
/** @var ?array $mapCenter */
$base = 't/' . $tour['token'];
$showPos = Settings::bool('show_position');
?>
<div class="card-head">
  <div>
    <h1><?= e($tour['name']) ?></h1>
    <p class="hint">Teilnehmer: <strong><?= e($outing['teilnehmer']) ?></strong> · <a href="<?= e(url($base, ['teilnehmer' => 1])) ?>">ändern</a></p>
  </div>
</div>
<?php if (($tour['beschreibung'] ?? '') !== ''): ?><p><?= nl2br(e((string) $tour['beschreibung'])) ?></p><?php endif; ?>
<p><progress max="<?= max(1, $total) ?>" value="<?= (int) $done ?>"></progress> <strong><?= (int) $done ?> von <?= (int) $total ?></strong> geprüft</p>

<?php $open = $total - $done; ?>
<div class="filter-switch" role="group" aria-label="Anzeige" data-tour-filter="<?= e((string) $tour['token']) ?>">
  <a class="btn btn-secondary" href="<?= e(url($base, ['nur' => 'alle'])) ?>" data-filter="alle" aria-pressed="<?= $onlyOpen ? 'false' : 'true' ?>">Alle (<?= (int) $total ?>)</a>
  <a class="btn btn-secondary" href="<?= e(url($base, ['nur' => 'offen'])) ?>" data-filter="offen" aria-pressed="<?= $onlyOpen ? 'true' : 'false' ?>">Nur offene (<?= (int) $open ?>)</a>
</div>

<div class="split" id="split" data-view="list">
  <div class="split-tabs" role="group" aria-label="Ansicht">
    <button type="button" class="btn btn-secondary" data-view="list" aria-pressed="true">Liste</button>
    <button type="button" class="btn btn-secondary" data-view="map" aria-pressed="false">Karte</button>
  </div>

  <div class="pane pane-list">
    <?php if (!$hydrants && $onlyOpen): ?>
      <div class="card"><p>Alle Hydranten dieser Tour sind geprüft.</p></div>
    <?php elseif (!$hydrants): ?>
      <div class="card"><p>Dieser Tour sind noch keine Hydranten zugeordnet.</p></div>
    <?php else: ?>
      <ul class="tour-hydrants">
        <?php foreach ($hydrants as $h):
          $res = $h['rev_ergebnis'];
          $cover = $covers[(int) $h['id']] ?? null; ?>
          <li class="card" id="h<?= (int) $h['id'] ?>">
            <?php if ($cover !== null): ?><img class="thumb" src="<?= e(url($base . '/bild/' . $cover . '/thumb')) ?>" alt="" loading="lazy"><?php endif; ?>
            <div class="th-body">
              <a class="th-title" href="<?= e(url($base . '/h/' . (int) $h['id'])) ?>"><?= e($h['kennung']) ?></a>
              <div class="hint"><?= e(Hydrant::typLabel($h['typ'])) ?><?= $showPos && $h['position_art'] ? ' · ' . e(Hydrant::lageLabel($h['position_art'])) : '' ?></div>
              <div>
                <?php if ($res === Revision::DEFECT): ?><span class="badge badge-warn">Mangel</span>
                <?php elseif ($res === Revision::OK): ?><span class="badge badge-ok">in Ordnung</span>
                <?php else: ?><span class="badge">offen</span><?php endif; ?>
                <?php if ($h['rev_at'] !== null): ?><span class="hint"><?= e(format_dt($h['rev_at'])) ?></span><?php endif; ?>
              </div>
            </div>
            <div class="th-actions">
              <a class="btn btn-primary btn-sm" href="<?= e(url($base . '/h/' . (int) $h['id'])) ?>"><?= $res === null ? 'Prüfen' : 'Erneut prüfen' ?></a>
              <?php if ($h['lat'] !== null && $h['lon'] !== null): ?>
                <button type="button" class="btn btn-secondary btn-sm" data-focus="<?= (int) $h['id'] ?>">Auf Karte</button>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <div class="pane pane-map">
    <div class="card card-flush">
      <div id="map" class="map map-overview js-map" data-mode="overview" data-center="<?= e($mapCenter ? (string) json_encode([$mapCenter[0], $mapCenter[1]]) : '') ?>" data-zoom="<?= (int) ($mapCenter[2] ?? 16) ?>" data-points="<?= e((string) json_encode($points, JSON_UNESCAPED_UNICODE)) ?>" tabindex="0" aria-label="Karte der Tour"></div>
      <p class="hint map-note">Farben: <span class="legend legend-offen">offen</span> <span class="legend legend-ok">in Ordnung</span> <span class="legend legend-mangel">Mangel</span></p>
    </div>
  </div>
</div>
