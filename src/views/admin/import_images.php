<?php
/** @var string $mode preview|progress|done */
/** @var string $token */
/** @var bool $writable */
?>
<h1><?= e($title) ?></h1>

<?php if (!$writable): ?>
  <div class="alert alert-error" role="alert">Der Importordner storage/import/ ist für die Anwendung nicht beschreibbar. Verarbeitete Dateien lassen sich dann nicht entfernen und würden bei einem erneuten Import doppelt übernommen. Bitte zuerst die Schreibrechte prüfen.</div>
<?php endif; ?>

<?php if ($mode === 'preview'):
    $ok = count(array_filter($checks, static fn ($c) => $c['error'] === null));
    $bad = count($checks) - $ok; ?>
  <div class="card">
    <div class="dashboard-stats">
      <div class="stat"><strong><?= $ok ?></strong><span>bereit</span></div>
      <div class="stat"><strong><?= $bad ?></strong><span>nicht verarbeitbar</span></div>
      <?php if ($unused): ?><div class="stat"><strong><?= count($unused) ?></strong><span>Dateien ohne Zuordnung</span></div><?php endif; ?>
    </div>
    <form method="post" action="<?= e(url('admin/import/bilder/ausfuehren')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <p class="actions">
        <?php if ($ok > 0): ?><button type="submit" class="btn btn-primary"><?= $ok ?> Bilder importieren</button><?php endif; ?>
        <a class="btn btn-secondary" href="<?= e(url('admin/import')) ?>">Abbrechen</a>
      </p>
      <p class="hint">Nicht verarbeitbare Zeilen werden übersprungen, ihre Dateien bleiben im Importordner.</p>
    </form>
  </div>
  <div class="card">
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Zeile</th><th>Kennung</th><th>Datei</th><th>Beschriftung</th><th>Prüfung</th></tr></thead>
        <tbody>
        <?php foreach ($checks as $c): $r = $c['row']; ?>
          <tr>
            <td data-label="Zeile"><?= (int) $r['line'] ?></td>
            <td data-label="Kennung"><?= e($r['kennung']) ?></td>
            <td data-label="Datei"><?= e($r['datei']) ?></td>
            <td data-label="Beschriftung"><?= e($r['beschriftung']) ?></td>
            <td data-label="Prüfung"><?= $c['error'] === null ? '<span class="badge badge-ok">bereit</span>' : '<span class="text-error">' . e($c['error']) . '</span>' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php if ($unused): ?>
    <div class="card">
      <h2>Dateien im Importordner ohne Zeile in der CSV</h2>
      <p class="hint">Diese Dateien werden nicht angefasst.</p>
      <p><?= e(implode(', ', array_slice($unused, 0, 60))) ?><?= count($unused) > 60 ? ' … (und ' . (count($unused) - 60) . ' weitere)' : '' ?></p>
    </div>
  <?php endif; ?>

<?php else:
    $okCount = count(array_filter($results, static fn ($r) => $r['ok']));
    $failed = array_filter($results, static fn ($r) => !$r['ok']); ?>
  <div class="card">
    <p><strong><?= count($results) ?></strong> von <strong><?= (int) $total ?></strong> Zeilen verarbeitet, davon <strong><?= $okCount ?></strong> Bilder importiert<?= $failed ? ', <strong>' . count($failed) . '</strong> nicht verarbeitbar' : '' ?>.</p>
    <?php if ($mode === 'progress'): ?>
      <form method="post" action="<?= e(url('admin/import/bilder/ausfuehren')) ?>" data-autosubmit>
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <p class="hint">Die Verarbeitung läuft in Etappen weiter. Bitte die Seite geöffnet lassen.</p>
        <button type="submit" class="btn btn-primary">Weiter</button>
      </form>
    <?php else: ?>
      <p class="actions">
        <a class="btn btn-primary" href="<?= e(url('hydranten')) ?>">Zu den Hydranten</a>
        <a class="btn btn-secondary" href="<?= e(url('admin/import')) ?>">Zurück zu Import und Export</a>
      </p>
      <?php if ($failed): ?><p class="hint">Dateien der nicht verarbeiteten Zeilen liegen weiterhin im Importordner.</p><?php endif; ?>
    <?php endif; ?>
  </div>
  <?php if ($failed): ?>
    <div class="card">
      <h2>Nicht verarbeitet</h2>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Zeile</th><th>Kennung</th><th>Datei</th><th>Grund</th></tr></thead>
          <tbody>
          <?php foreach ($failed as $r): ?>
            <tr><td data-label="Zeile"><?= (int) $r['line'] ?></td><td data-label="Kennung"><?= e($r['kennung']) ?></td><td data-label="Datei"><?= e($r['datei']) ?></td><td data-label="Grund"><?= e($r['msg']) ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
  <?php $warn = array_filter($results, static fn ($r) => $r['ok'] && $r['msg'] !== 'Importiert.'); ?>
  <?php if ($warn): ?>
    <div class="card"><h2>Hinweise</h2><ul><?php foreach ($warn as $r): ?><li><?= e($r['datei']) ?>: <?= e($r['msg']) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
<?php endif; ?>
