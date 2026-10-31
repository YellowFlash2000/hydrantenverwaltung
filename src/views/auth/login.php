<?php /** @var string $email */ /** @var ?string $error */ ?>
<div class="card card-narrow">
  <h1>Anmelden</h1>
  <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="<?= e(url('login')) ?>" autocomplete="on">
    <?= csrf_field() ?>
    <div class="form-group">
      <label for="email">E-Mail-Adresse</label>
      <input type="email" id="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="username">
    </div>
    <div class="form-group">
      <label for="password">Passwort</label>
      <input type="password" id="password" name="password" required autocomplete="current-password">
    </div>
    <button type="submit" class="btn btn-primary btn-block">Anmelden</button>
  </form>
  <p class="hint center"><a href="<?= e(url('passwort/vergessen')) ?>">Passwort vergessen?</a></p>
</div>
