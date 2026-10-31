<?php
/** @var array $f */
/** @var array $rows */
/** @var array $objects */
/** @var int $total */
/** @var int $pages */
/** @var string[] $users */
/** @var int $all */
/** @var int $retention */
$query = AuditLog::toQuery($f);
$pageUrl = static fn (int $p): string => url('admin/protokoll', $query + ($p > 1 ? ['seite' => (string) $p] : []));
$filtered = $query !== [];
?>
<div class="card-head">
  <h1>Protokoll</h1>
  <a class="btn btn-secondary" href="<?= e(url('admin/protokoll.csv', $query)) ?>">Als CSV herunterladen</a>
</div>
<p class="hint">Hier steht, wer wann was getan hat. Aufbewahrung: <strong><?= e(AuditLog::RETENTION[$retention]) ?></strong> (änderbar unter <a href="<?= e(url('admin/einstellungen#protokoll')) ?>">Einstellungen</a>).</p>

<form method="get" action="<?= e(url('admin/protokoll')) ?>" class="card filter-bar">
  <?php if ($f['objekt'] !== ''): ?><input type="hidden" name="objekt" value="<?= e($f['objekt']) ?>"><?php endif; ?>
  <div class="form-row">
    <div class="form-group">
      <label for="von">Von</label>
      <input type="date" id="von" name="von" value="<?= e($f['von']) ?>">
    </div>
    <div class="form-group">
      <label for="bis">Bis</label>
      <input type="date" id="bis" name="bis" value="<?= e($f['bis']) ?>">
    </div>
    <div class="form-group">
      <label for="gruppe">Art</label>
      <select id="gruppe" name="gruppe">
        <option value="">alle</option>
        <?php foreach (AuditLog::GROUPS as $k => [$label]): ?>
          <option value="<?= e($k) ?>" <?= $f['gruppe'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label for="benutzer">Benutzer</label>
      <select id="benutzer" name="benutzer">
        <option value="">alle</option>
        <?php foreach ($users as $u): ?>
          <option value="<?= e($u) ?>" <?= $f['benutzer'] === $u ? 'selected' : '' ?>><?= e($u) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group grow">
      <label for="q">Suche</label>
      <input type="search" id="q" name="q" value="<?= e($f['q']) ?>" placeholder="in Details und Benutzer" autocomplete="off">
    </div>
  </div>
  <?php if ($f['objekt'] !== ''): ?><p class="hint">Gefiltert auf ein einzelnes Objekt (<?= e($f['objekt']) ?>).</p><?php endif; ?>
  <div class="actions filter-actions">
    <button type="submit" class="btn btn-primary">Filtern</button>
    <?php if ($filtered): ?><a class="btn btn-secondary" href="<?= e(url('admin/protokoll')) ?>">Zurücksetzen</a><?php endif; ?>
  </div>
</form>

<p class="hint result-count"><?= $total ?> <?= $total === 1 ? 'Eintrag' : 'Einträge' ?><?= $filtered ? ' (von ' . $all . ' insgesamt)' : '' ?> · Seite <?= (int) $f['seite'] ?> von <?= $pages ?></p>

<?php if (!$rows): ?>
  <div class="card"><p>Keine Einträge gefunden.</p></div>
<?php else: ?>
  <div class="card card-flush">
    <div class="table-wrap">
      <table class="table table-audit">
        <thead><tr><th>Zeit</th><th>Benutzer</th><th>Aktion</th><th>Objekt</th><th>Details</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): $o = $objects[(int) $r['id']]; $fail = $r['action'] === 'login_fehlgeschlagen'; ?>
          <tr class="<?= $fail ? 'audit-fail' : '' ?>">
            <td data-label="Zeit"><?= e(date('d.m.Y H:i:s', (int) strtotime((string) $r['created_at']))) ?></td>
            <td data-label="Benutzer"><?= e((string) $r['user_label']) ?></td>
            <td data-label="Aktion"><?= $fail ? '<span class="badge badge-off">' . e(AuditLog::label((string) $r['action'])) . '</span>' : e(AuditLog::label((string) $r['action'])) ?></td>
            <td data-label="Objekt"><?php if ($o['href'] !== null): ?><a href="<?= e($o['href']) ?>"><?= e($o['text']) ?></a><?php else: ?><?= e($o['text']) ?><?php endif; ?></td>
            <td data-label="Details"><?= nl2br(e((string) ($r['details'] ?? ''))) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php if ($pages > 1): ?>
    <nav class="pager" aria-label="Seiten">
      <?php if ($f['seite'] > 1): ?><a class="btn btn-secondary" href="<?= e($pageUrl($f['seite'] - 1)) ?>">← Neuere</a><?php endif; ?>
      <span class="hint">Seite <?= (int) $f['seite'] ?> von <?= $pages ?></span>
      <?php if ($f['seite'] < $pages): ?><a class="btn btn-secondary" href="<?= e($pageUrl($f['seite'] + 1)) ?>">Ältere →</a><?php endif; ?>
    </nav>
  <?php endif; ?>
<?php endif; ?>
