<?php
/** @var ?array $target */
/** @var array $form */
/** @var string[] $errors */
/** @var bool $self */
$action = $target === null ? url('admin/benutzer/neu') : url('admin/benutzer/' . (int) $target['id']);
?>
<div class="card">
  <h1><?= e($title) ?></h1>
  <?php if ($errors): ?>
    <div class="alert alert-error" role="alert">
      <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="post" action="<?= e($action) ?>">
    <?= csrf_field() ?>
    <div class="form-group">
      <label for="name">Name</label>
      <input type="text" id="name" name="name" value="<?= e($form['name']) ?>" required maxlength="120" autofocus>
    </div>
    <div class="form-group">
      <label for="email">E-Mail-Adresse (Login)</label>
      <input type="email" id="email" name="email" value="<?= e($form['email']) ?>" required maxlength="190">
    </div>
    <div class="form-group">
      <label for="rolle">Rolle</label>
      <select id="rolle" name="rolle" <?= $self ? 'disabled' : '' ?>>
        <?php foreach (Auth::ROLE_LABELS as $key => $label): ?>
          <option value="<?= e($key) ?>" <?= $form['rolle'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if ($self): ?><p class="hint">Die eigene Rolle kann nicht geändert werden.</p><?php endif; ?>
      <ul class="hint role-help">
        <?php foreach (Auth::ROLE_LABELS as $key => $label): ?>
          <li><strong><?= e($label) ?>:</strong> <?= e(Auth::ROLE_HELP[$key]) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php if ($target !== null): ?>
      <div class="form-group">
        <label class="check">
          <input type="checkbox" name="aktiv" value="1" <?= (int) $form['aktiv'] === 1 ? 'checked' : '' ?> <?= $self ? 'disabled' : '' ?>>
          Konto aktiv (gesperrte Benutzer können sich nicht anmelden)
        </label>
      </div>
    <?php else: ?>
      <p class="hint">Der Benutzer erhält per E-Mail einen Link, über den er sein Passwort selbst festlegt.</p>
    <?php endif; ?>

    <div class="actions">
      <button type="submit" class="btn btn-primary"><?= $target === null ? 'Anlegen und einladen' : 'Speichern' ?></button>
      <a class="btn btn-secondary" href="<?= e(url('admin/benutzer')) ?>">Abbrechen</a>
    </div>
  </form>
</div>

<?php if ($target !== null && (int) $target['aktiv'] === 1): ?>
  <div class="card">
    <h2><?= $target['password_hash'] === null ? 'Einladung' : 'Passwort zurücksetzen' ?></h2>
    <?php if ($target['password_hash'] === null): ?>
      <p>Dieser Benutzer hat noch kein Passwort festgelegt. Sie können die Einladung erneut senden.</p>
    <?php else: ?>
      <p>Der Benutzer erhält eine E-Mail mit einem Link, über den er ein neues Passwort festlegt. Das bisherige Passwort bleibt bis dahin gültig.</p>
    <?php endif; ?>
    <form method="post" action="<?= e(url('admin/benutzer/' . (int) $target['id'] . '/link')) ?>">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-secondary"><?= $target['password_hash'] === null ? 'Einladung erneut senden' : 'Reset-Link senden' ?></button>
    </form>
  </div>
<?php endif; ?>
