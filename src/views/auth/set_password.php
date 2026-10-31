<?php /** @var array $user */ /** @var string $token */ /** @var ?string $error */ ?>
<div class="card card-narrow">
  <h1>Passwort festlegen</h1>
  <p>Hallo <?= e($user['name']) ?>, bitte legen Sie ein Passwort für <strong><?= e($user['email']) ?></strong> fest.</p>
  <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="<?= e(url('passwort/setzen/' . $token)) ?>">
    <?= csrf_field() ?>
    <div class="form-group">
      <label for="password">Neues Passwort</label>
      <input type="password" id="password" name="password" required minlength="10" autofocus autocomplete="new-password">
      <p class="hint">Mindestens 10 Zeichen. Eine Passphrase aus mehreren Wörtern ist sicher und gut merkbar.</p>
    </div>
    <div class="form-group">
      <label for="password2">Passwort wiederholen</label>
      <input type="password" id="password2" name="password2" required minlength="10" autocomplete="new-password">
    </div>
    <button type="submit" class="btn btn-primary btn-block">Passwort speichern</button>
  </form>
</div>
