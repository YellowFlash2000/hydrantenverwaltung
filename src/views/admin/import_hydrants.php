<?php
/** @var array $plan */
/** @var string $token */
/** @var string $default */
/** @var bool $blocked */
$c = $plan['counts'];
$statusLabel = [
    'new'       => ['Neu anlegen', 'badge-ok'],
    'update'    => ['Aktualisieren', 'badge-ok'],
    'unchanged' => ['Unverändert', ''],
    'ignore'    => ['Ignorieren', ''],
    'ask'       => ['Entscheidung nötig', 'badge-warn'],
    'error'     => ['Fehler, wird übersprungen', 'badge-off'],
];
$colNames = [
    'kennung' => 'Kennung', 'typ' => 'Typ', 'durchmesser_dn' => 'Durchmesser', 'durchfluss_lmin' => 'Durchfluss', 'druck_bar' => 'Druck',
    'position_art' => 'Position', 'tour' => 'Tour', 'lat' => 'Breite', 'lon' => 'Länge', 'bemerkung' => 'Bemerkung', 'aktiv' => 'Status',
];
?>
<h1>Import prüfen</h1>

<?php if ($blocked): ?>
  <div class="alert alert-error" role="alert">
    Der Import wurde noch nicht ausgeführt.
    <?php if ($plan['undecided'] > 0): ?>Für <?= (int) $plan['undecided'] ?> vorhandene Kennungen fehlt noch eine Entscheidung. <?php endif; ?>
    <?php if ($plan['problems'] > 0): ?>Bei <?= (int) $plan['problems'] ?> Hydranten, die als neu angelegt werden sollen, fehlt eine gültige neue Kennung. <?php endif; ?>
  </div>
<?php endif; ?>

<div class="card">
  <h2>Ergebnis der Prüfung</h2>
  <div class="dashboard-stats">
    <div class="stat"><strong><?= (int) $c['new'] ?></strong><span>neu</span></div>
    <div class="stat"><strong><?= (int) $c['update'] ?></strong><span>aktualisiert</span></div>
    <div class="stat"><strong><?= (int) $c['unchanged'] ?></strong><span>unverändert</span></div>
    <div class="stat"><strong><?= (int) $c['ignore'] ?></strong><span>ignoriert</span></div>
    <div class="stat"><strong><?= (int) $c['error'] ?></strong><span>fehlerhaft</span></div>
    <?php if ($c['ask'] > 0): ?><div class="stat"><strong><?= (int) $c['ask'] ?></strong><span>offen</span></div><?php endif; ?>
  </div>
  <?php if (!empty($plan['new_tours'])): ?>
    <div class="alert alert-warning" role="status">Diese Touren gibt es noch nicht und werden beim Import angelegt: <strong><?= e(implode(', ', $plan['new_tours'])) ?></strong></div>
  <?php endif; ?>
  <p class="hint">Erkannte Spalten: <?= e(implode(', ', array_map(static fn ($f) => $colNames[$f] ?? $f, $plan['columns']))) ?>.
    <?php if ($plan['ignored']): ?>Nicht übernommene Spalten: <?= e(implode(', ', array_filter($plan['ignored'], static fn ($x) => $x !== ''))) ?>.<?php endif; ?></p>
</div>

<form method="post" action="<?= e(url('admin/import/hydranten/ausfuehren')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="token" value="<?= e($token) ?>">
  <input type="hidden" name="default" value="<?= e($default) ?>">

  <?php $hasConflict = (bool) array_filter($plan['rows'], static fn ($r) => $r['conflict']); ?>
  <?php if ($hasConflict): ?>
    <div class="card">
      <h2>Kennung schon vorhanden</h2>
      <p class="hint">Diese Kennungen gibt es bereits. Bitte je Hydrant wählen, was geschehen soll.</p>
      <p class="actions">
        Alle setzen auf:
        <?php foreach (HydrantImport::MODES as $k => $label): ?>
          <button type="button" class="btn btn-secondary btn-sm" data-set-all="<?= e($k) ?>"><?= e($label) ?></button>
        <?php endforeach; ?>
      </p>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Zeile</th><th>Kennung</th><th>Änderungen laut Datei</th><th>Entscheidung</th></tr></thead>
          <tbody>
          <?php foreach ($plan['rows'] as $r): if (!$r['conflict']) { continue; } ?>
            <tr>
              <td data-label="Zeile"><?= (int) $r['line'] ?></td>
              <td data-label="Kennung"><strong><?= e($r['kennung']) ?></strong></td>
              <td data-label="Änderungen">
                <?php if ($r['mode'] === 'update' && $r['status'] === 'update'): ?><?= e($r['changes']) ?>
                <?php elseif ($r['mode'] === 'update' && $r['status'] === 'unchanged'): ?><span class="hint">keine Änderung</span>
                <?php elseif ($r['mode'] === 'update' && $r['status'] === 'error'): ?><span class="text-error"><?= e(implode(' ', $r['errors'])) ?></span>
                <?php else: ?><span class="hint">–</span><?php endif; ?>
              </td>
              <td data-label="Entscheidung">
                <select name="mode[<?= (int) $r['line'] ?>]" class="import-mode" aria-label="Entscheidung für <?= e($r['kennung']) ?>">
                  <?php if ($r['mode'] === 'ask'): ?><option value="ask" selected>Bitte wählen …</option><?php endif; ?>
                  <?php foreach (HydrantImport::MODES as $k => $label): ?>
                    <option value="<?= e($k) ?>" <?= $r['mode'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                  <?php endforeach; ?>
                </select>
                <div class="import-newkennung" <?= $r['mode'] === 'new' ? '' : 'hidden' ?>>
                  <input type="text" name="newkennung[<?= (int) $r['line'] ?>]" value="<?= e($r['newkennung']) ?>" maxlength="190" placeholder="Neue Kennung" aria-label="Neue Kennung für <?= e($r['kennung']) ?>">
                  <?php if ($r['mode'] === 'new' && $r['errors']): ?><div class="text-error"><?= e(implode(' ', $r['errors'])) ?></div><?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

  <?php $errorRows = array_filter($plan['rows'], static fn ($r) => $r['status'] === 'error' && !$r['conflict']); ?>
  <?php if ($errorRows): ?>
    <div class="card">
      <h2>Fehlerhafte Zeilen (werden übersprungen)</h2>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Zeile</th><th>Kennung</th><th>Problem</th></tr></thead>
          <tbody>
          <?php foreach ($errorRows as $r): ?>
            <tr><td data-label="Zeile"><?= (int) $r['line'] ?></td><td data-label="Kennung"><?= e($r['kennung']) ?></td><td data-label="Problem"><?= e(implode(' ', $r['errors'])) ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

  <div class="card">
    <h2>Alle Zeilen</h2>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Zeile</th><th>Kennung</th><th>Aktion</th><th>Einzelheiten</th></tr></thead>
        <tbody>
        <?php foreach ($plan['rows'] as $r): [$label, $cls] = $statusLabel[$r['status']] ?? ['', '']; ?>
          <tr>
            <td data-label="Zeile"><?= (int) $r['line'] ?></td>
            <td data-label="Kennung"><?= e($r['kennung']) ?><?= $r['status'] === 'new' && $r['conflict'] ? ' → ' . e((string) $r['v']['kennung']) : '' ?></td>
            <td data-label="Aktion"><span class="badge <?= e($cls) ?>"><?= e($label) ?></span></td>
            <td data-label="Einzelheiten">
              <?php if ($r['status'] === 'new'): ?>
                <?= e(Hydrant::typLabel($r['v']['typ'])) ?><?= $r['v']['durchmesser_dn'] !== null ? ', DN ' . (int) $r['v']['durchmesser_dn'] : '' ?><?= $r['v']['lat'] !== null ? ', mit Koordinaten' : ', ohne Koordinaten' ?><?= $r['aktiv'] === 0 ? ', deaktiviert' : '' ?>
              <?php elseif ($r['status'] === 'update'): ?><?= e($r['changes']) ?>
              <?php elseif ($r['status'] === 'error'): ?><span class="text-error"><?= e(implode(' ', $r['errors'])) ?></span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <p class="actions">
    <button type="submit" name="aktion" value="ausfuehren" class="btn btn-primary">Import ausführen</button>
    <button type="submit" name="aktion" value="pruefen" class="btn btn-secondary">Auswahl neu prüfen</button>
    <a class="btn btn-secondary" href="<?= e(url('admin/import')) ?>">Abbrechen</a>
  </p>
</form>
