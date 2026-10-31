<?php /** @var array $users */ /** @var array $me */ ?>
<div class="card">
  <div class="card-head">
    <h1>Benutzer</h1>
    <a class="btn btn-primary" href="<?= e(url('admin/benutzer/neu')) ?>">Benutzer anlegen</a>
  </div>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr><th>Name</th><th>E-Mail</th><th>Rolle</th><th>Status</th><th>Letzte Anmeldung</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td data-label="Name"><?= e($u['name']) ?><?= (int) $u['id'] === (int) $me['id'] ? ' <span class="badge">Sie</span>' : '' ?></td>
          <td data-label="E-Mail"><?= e($u['email']) ?></td>
          <td data-label="Rolle"><?= e(Auth::ROLE_LABELS[$u['rolle']] ?? $u['rolle']) ?></td>
          <td data-label="Status">
            <?php if ((int) $u['aktiv'] !== 1): ?>
              <span class="badge badge-off">gesperrt</span>
            <?php elseif ((int) $u['pending'] === 1): ?>
              <span class="badge badge-warn">Einladung offen</span>
            <?php else: ?>
              <span class="badge badge-ok">aktiv</span>
            <?php endif; ?>
          </td>
          <td data-label="Letzte Anmeldung"><?= e(format_dt($u['last_login'])) ?></td>
          <td class="cell-action"><a class="btn btn-secondary btn-sm" href="<?= e(url('admin/benutzer/' . (int) $u['id'])) ?>">Bearbeiten</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
