<?php /** @var array $user */ /** @var array $counts */ /** @var ?array $campaign */ /** @var array $tours */ /** @var array $progress */ ?>
<div class="card">
  <h1>Willkommen, <?= e($user['name']) ?></h1>
  <p>Sie sind angemeldet als <strong><?= e(Auth::ROLE_LABELS[$user['rolle']] ?? $user['rolle']) ?></strong>
    (<?= e(Auth::ROLE_HELP[$user['rolle']] ?? '') ?>).</p>

  <div class="dashboard-stats">
    <div class="stat"><strong><?= (int) $counts['active'] ?></strong><span>aktive Hydranten</span></div>
    <?php if ($counts['inactive'] > 0): ?><div class="stat"><strong><?= (int) $counts['inactive'] ?></strong><span>deaktiviert</span></div><?php endif; ?>
    <?php if ($counts['no_coords'] > 0): ?><div class="stat"><strong><?= (int) $counts['no_coords'] ?></strong><span>ohne Koordinaten</span></div><?php endif; ?>
  </div>

  <p class="actions">
    <a class="btn btn-primary" href="<?= e(url('hydranten')) ?>">Hydranten ansehen</a>
    <?php if (Auth::hasRole('editor')): ?>
      <a class="btn btn-secondary" href="<?= e(url('hydranten/neu')) ?>">Hydrant anlegen</a>
    <?php endif; ?>
    <?php if (Auth::hasRole('admin')): ?>
      <a class="btn btn-secondary" href="<?= e(url('admin/benutzer')) ?>">Benutzer verwalten</a>
      <a class="btn btn-secondary" href="<?= e(url('admin/import')) ?>">Import/Export</a>
      <a class="btn btn-secondary" href="<?= e(url('admin/einstellungen')) ?>">Einstellungen</a>
    <?php endif; ?>
  </p>
</div>

<div class="card">
  <h2>Prüfdurchgang</h2>
  <?php if ($campaign === null): ?>
    <p class="hint">Zurzeit ist kein Prüfdurchgang aktiv.</p>
    <?php if (Auth::hasRole('editor')): ?><p><a class="btn btn-secondary" href="<?= e(url('pruefungen/neu')) ?>">Prüfdurchgang anlegen</a></p><?php endif; ?>
  <?php else: ?>
    <p><strong><?= e($campaign['name']) ?></strong> · seit <?= e(format_dt($campaign['start_date'], false)) ?></p>
    <?php if (!$tours): ?>
      <p class="hint">Es gibt noch keine Touren.</p>
    <?php else: ?>
      <ul class="tour-progress">
        <?php foreach ($tours as $t): $p = $progress[(int) $t['id']] ?? ['total' => 0, 'done' => 0, 'defects' => 0]; ?>
          <li>
            <span class="tour-name"><?= Auth::hasRole('editor') ? '<a href="' . e(url('touren/' . (int) $t['id'])) . '">' . e($t['name']) . '</a>' : e($t['name']) ?></span>
            <progress max="<?= max(1, (int) $p['total']) ?>" value="<?= (int) $p['done'] ?>"></progress>
            <span class="hint"><?= (int) $p['done'] ?> von <?= (int) $p['total'] ?> geprüft<?= $p['defects'] > 0 ? ', ' . (int) $p['defects'] . ' mit Mangel' : '' ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  <?php endif; ?>
</div>
