<div class="card card-narrow">
  <h1>Passwort vergessen</h1>
  <p>Geben Sie Ihre E-Mail-Adresse ein. Wir senden Ihnen einen Link, über den Sie ein neues Passwort festlegen können.</p>
  <form method="post" action="<?= e(url('passwort/vergessen')) ?>">
    <?= csrf_field() ?>
    <div class="form-group">
      <label for="email">E-Mail-Adresse</label>
      <input type="email" id="email" name="email" required autofocus autocomplete="username">
    </div>
    <button type="submit" class="btn btn-primary btn-block">Link anfordern</button>
  </form>
  <p class="hint center"><a href="<?= e(url('login')) ?>">Zurück zur Anmeldung</a></p>
</div>
