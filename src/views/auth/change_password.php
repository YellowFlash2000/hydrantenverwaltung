<?php /** @var ?string $error */ ?>
<div class="card card-narrow">
  <h1>Passwort ändern</h1>
  <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="<?= e(url('passwort')) ?>">
    <?= csrf_field() ?>
    <div class="form-group">
      <label for="current">Aktuelles Passwort</label>
      <input type="password" id="current" name="current" required autofocus autocomplete="current-password">
    </div>
    <div class="form-group">
      <label for="password">Neues Passwort</label>
      <input type="password" id="password" name="password" required minlength="10" autocomplete="new-password">
      <p class="hint">Mindestens 10 Zeichen.</p>
    </div>
    <div class="form-group">
      <label for="password2">Neues Passwort wiederholen</label>
      <input type="password" id="password2" name="password2" required minlength="10" autocomplete="new-password">
    </div>
    <button type="submit" class="btn btn-primary">Passwort ändern</button>
    <a class="btn btn-secondary" href="<?= e(url('')) ?>">Abbrechen</a>
  </form>
</div>
