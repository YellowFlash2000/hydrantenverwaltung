<?php
/** @var array $rows */
/** @var array $points */
/** @var array $f */
/** @var int $total */
/** @var int $active */
/** @var array $tours */
/** @var ?array $campaign */
/** @var bool $hasManual */
$canEdit = Auth::hasRole('editor');
$pressure = Settings::bool('show_pressure');
$showFlow = Settings::bool('show_flow');
$showPos = Settings::bool('show_position');
$noCoords = count($rows) - count($points);
$manualDefect = static fn (array $r): bool => $r['camp_all'] === null && $r['ergebnis_all'] === Revision::DEFECT;
$showCheck = $campaign !== null || $hasManual;
$filtered = $f['q'] !== '' || $f['typ'] !== '' || $f['lage'] !== '' || $f['status'] !== 'aktiv' || $f['tour'] !== '' || $f['pruef'] !== '';
?>
<div class="card-head">
  <h1>Hydranten</h1>
  <div class="actions">
  <?php if (Auth::hasRole('admin')): ?>
    <a class="btn btn-secondary" href="<?= e(url('admin/export/osm?' . http_build_query(HydrantFilter::toQuery($f)))) ?>">Für OpenStreetMap exportieren</a>
  <?php endif; ?>
  <?php if ($canEdit): ?>
    <a class="btn btn-primary" href="<?= e(url('hydranten/neu')) ?>">Hydrant anlegen</a>
  <?php endif; ?>
  </div>
</div>

<form method="get" action="<?= e(url('hydranten')) ?>" class="card filter-bar">
  <div class="form-row">
    <div class="form-group grow">
      <label for="q">Suche</label>
      <input type="search" id="q" name="q" value="<?= e($f['q']) ?>" placeholder="Kennung oder Bemerkung" autocomplete="off">
    </div>
    <div class="form-group">
      <label for="typ">Typ</label>
      <select id="typ" name="typ">
        <option value="">alle</option>
        <?php foreach (HydrantType::labels() as $k => $label): ?>
          <option value="<?= e($k) ?>" <?= $f['typ'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if ($showPos): ?>
    <div class="form-group">
      <label for="lage">Position</label>
      <select id="lage" name="lage">
        <option value="">alle</option>
        <?php foreach (HydrantPosition::labels() as $k => $label): ?>
          <option value="<?= e($k) ?>" <?= $f['lage'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div class="form-group">
      <label for="status">Status</label>
      <select id="status" name="status">
        <option value="aktiv" <?= $f['status'] === 'aktiv' ? 'selected' : '' ?>>aktive</option>
        <option value="inaktiv" <?= $f['status'] === 'inaktiv' ? 'selected' : '' ?>>deaktivierte</option>
        <option value="alle" <?= $f['status'] === 'alle' ? 'selected' : '' ?>>alle</option>
      </select>
    </div>
    <?php if ($tours): ?>
    <div class="form-group">
      <label for="tour">Tour</label>
      <select id="tour" name="tour">
        <option value="">alle</option>
        <option value="none" <?= $f['tour'] === 'none' ? 'selected' : '' ?>>ohne Tour</option>
        <?php foreach ($tours as $t): ?>
          <option value="<?= (int) $t['id'] ?>" <?= $f['tour'] === (string) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div class="form-group">
      <label for="pruef">Prüfstatus</label>
      <select id="pruef" name="pruef">
        <option value="">alle</option>
        <?php foreach (HydrantFilter::pruefOptions() as $k => $label): ?>
          <option value="<?= e($k) ?>" <?= $f['pruef'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="actions filter-actions">
    <button type="submit" class="btn btn-primary">Filtern</button>
    <?php if ($filtered): ?><a class="btn btn-secondary" href="<?= e(url('hydranten')) ?>">Zurücksetzen</a><?php endif; ?>
  </div>
</form>

<p class="hint result-count">
  <?= count($rows) ?> <?= count($rows) === 1 ? 'Treffer' : 'Treffer' ?> · insgesamt <?= $active ?> aktive<?= $total > $active ? ' und ' . ($total - $active) . ' deaktivierte' : '' ?> Hydranten
</p>

<div class="split" id="split" data-view="list">
  <div class="split-tabs" role="group" aria-label="Ansicht">
    <button type="button" class="btn btn-secondary" data-view="list" aria-pressed="true">Liste</button>
    <button type="button" class="btn btn-secondary" data-view="map" aria-pressed="false">Karte</button>
  </div>

  <div class="pane pane-list">
    <?php if (!$rows): ?>
      <div class="card">
        <p><strong>Keine Hydranten gefunden.</strong></p>
        <?php if ($filtered): ?>
          <p class="hint">Bitte Suche oder Filter ändern.</p>
        <?php elseif ($canEdit): ?>
          <p class="hint">Legen Sie den ersten Hydranten über „Hydrant anlegen“ an.</p>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <?php $bulk = $canEdit && $tours; ?>
      <div class="card card-flush">
        <?php if ($bulk): ?>
        <form method="post" action="<?= e(url('hydranten/zuweisen')) ?>" id="bulk-form">
          <?= csrf_field() ?>
          <input type="hidden" name="back_query" value="<?= e((string) ($_SERVER['QUERY_STRING'] ?? '')) ?>">
        <?php endif; ?>
        <p class="legend-bar" aria-label="Legende der Typen">
          <span class="legend-title">Typ:</span>
          <?php foreach (HydrantType::all() as $tk => $tr): if ((int) $tr['aktiv'] !== 1 && !isset($usedTypes[$tk])) { continue; } ?>
            <span class="legend-item"><?= HydrantType::svg((string) $tr['symbol'], 14) ?> <?= e($tr['name']) ?></span>
          <?php endforeach; ?>
        </p>
        <div class="table-wrap">
          <table class="table table-hydrants">
            <thead>
              <tr>
                <?php if ($bulk): ?><th class="cell-check"><input type="checkbox" data-check-all aria-label="Alle auswählen"></th><?php endif; ?>
                <th class="cell-type">Typ</th><th>Kennung</th><th>DN</th><?php if ($showFlow): ?><th>Durchfluss</th><?php endif; ?><?php if ($showPos): ?><th>Position</th><?php endif; ?>
                <?php if ($pressure): ?><th>Druck</th><?php endif; ?>
                <?php if ($campaign !== null || $tours): ?><th>Tour</th><?php endif; ?>
                <?php if ($showCheck): ?><th>Prüfung</th><?php endif; ?>
                <th></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): $inactive = (int) $r['aktiv'] !== 1; ?>
              <tr class="<?= $inactive ? 'row-inactive' : '' ?>">
                <?php if ($bulk): ?><td class="cell-check"><input type="checkbox" name="ids[]" value="<?= (int) $r['id'] ?>" data-check aria-label="<?= e($r['kennung']) ?> auswählen"></td><?php endif; ?>
                <td data-label="Typ" class="cell-type"><?= HydrantType::icon($r['typ']) ?></td>
                <td data-label="Kennung" class="cell-main"><a href="<?= e(url('hydranten/' . (int) $r['id'])) ?>"><?= e($r['kennung']) ?></a><?= $inactive ? ' <span class="badge badge-off">deaktiviert</span>' : '' ?></td>
                <td data-label="DN"><?= $r['durchmesser_dn'] === null ? '–' : e((string) $r['durchmesser_dn']) ?></td>
                <?php if ($showFlow): ?><td data-label="Durchfluss"><?= e(Hydrant::flow($r)) ?></td><?php endif; ?>
                <?php if ($showPos): ?><td data-label="Position"><?= e(Hydrant::lageLabel($r['position_art'])) ?></td><?php endif; ?>
                <?php if ($pressure): ?><td data-label="Druck"><?= $r['druck_bar'] === null ? '–' : e(Hydrant::number($r['druck_bar'], 1)) . ' bar' ?></td><?php endif; ?>
                <?php if ($campaign !== null || $tours): ?>
                  <td data-label="Tour"><?= $r['tour_name'] === null ? '–' : e($r['tour_name']) ?></td>
                <?php endif; ?>
                <?php if ($showCheck): $manual = $manualDefect($r);
                  // Ein späterer manueller „in Ordnung“-Eintrag behebt den Mangel des Durchgangs
                  $res = $r['rev_ergebnis'] === Revision::DEFECT && $r['camp_all'] === null && $r['ergebnis_all'] === Revision::OK ? Revision::OK : $r['rev_ergebnis']; ?>
                  <td data-label="Prüfung"><?php
                    if ($campaign === null) { if (!$manual) { echo '<span class="hint">–</span>'; } }
                    elseif ($r['ct_id'] === null) { if (!$manual) { echo '<span class="hint" title="Die Tour gehört nicht zum aktiven Prüfdurchgang">–</span>'; } }
                    elseif ($res === Revision::DEFECT) { echo '<span class="badge badge-warn">Mangel</span>'; }
                    elseif ($res === Revision::OK) { echo '<span class="badge badge-ok">✓ geprüft</span>'; }
                    else { echo '<span class="badge">offen</span>'; }
                    if ($manual) { echo ' <span class="badge badge-warn" title="Der Mangel wurde direkt am Hydranten ohne Prüfdurchgang eingetragen und ist noch nicht behoben">Mangel (ohne Durchgang)</span>'; } ?></td>
                <?php endif; ?>
                <td class="cell-action">
                  <?php if ($r['lat'] !== null && $r['lon'] !== null): ?>
                    <button type="button" class="btn btn-secondary btn-sm" data-focus="<?= (int) $r['id'] ?>" title="Auf der Karte zeigen" aria-label="<?= e($r['kennung']) ?> auf der Karte zeigen">Karte</button>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if ($bulk): ?>
          <div class="bulk-bar">
            <label for="bulk-tour">Ausgewählte Hydranten</label>
            <select name="tour_id" id="bulk-tour">
              <option value="">Tour wählen …</option>
              <?php foreach ($tours as $t): ?><option value="<?= (int) $t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach; ?>
            </select>
            <button type="submit" name="aktion" value="zuweisen" class="btn btn-primary btn-sm">Tour zuweisen</button>
            <button type="submit" name="aktion" value="entfernen" class="btn btn-secondary btn-sm">Aus Tour nehmen</button>
            <span class="hint" id="bulk-count"></span>
          </div>
        </form>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="pane pane-map">
    <div class="card card-flush">
      <div id="map" class="map map-overview js-map" data-mode="overview" data-center="<?= e($mapCenter ? (string) json_encode([$mapCenter[0], $mapCenter[1]]) : '') ?>" data-zoom="<?= (int) ($mapCenter[2] ?? 16) ?>" data-points="<?= e((string) json_encode($points, JSON_UNESCAPED_UNICODE)) ?>" tabindex="0" aria-label="Karte der Hydranten"></div>
      <?php if ($noCoords > 0): ?>
        <p class="hint map-note"><?= $noCoords ?> <?= $noCoords === 1 ? 'Hydrant hat' : 'Hydranten haben' ?> keine Koordinaten und <?= $noCoords === 1 ? 'fehlt' : 'fehlen' ?> auf der Karte.</p>
      <?php endif; ?>
    </div>
  </div>
</div>
