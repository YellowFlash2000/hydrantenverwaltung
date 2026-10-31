<?php
/** @var ?array $tour */
/** @var array $form */
/** @var string[] $errors */
$action = $tour === null ? url('touren/neu') : url('touren/' . (int) $tour['id'] . '/bearbeiten');
?>
<h1><?= e($title) ?></h1>
<?php if ($errors): ?>
  <div class="alert alert-error" role="alert"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
<?php endif; ?>
<form method="post" action="<?= e($action) ?>" class="card">
  <?= csrf_field() ?>
  <div class="form-group">
    <label for="name">Name der Tour</label>
    <input type="text" id="name" name="name" value="<?= e($form['name']) ?>" maxlength="120" required autofocus placeholder="z. B. Tour Nord">
  </div>
  <div class="form-group">
    <label for="beschreibung">Beschreibung (optional)</label>
    <textarea id="beschreibung" name="beschreibung" rows="3" maxlength="2000" placeholder="z. B. Treffpunkt, Hinweise für die Teilnehmer"><?= e($form['beschreibung']) ?></textarea>
  </div>
  <p class="actions">
    <button type="submit" class="btn btn-primary">Speichern</button>
    <a class="btn btn-secondary" href="<?= e($tour === null ? url('touren') : url('touren/' . (int) $tour['id'])) ?>">Abbrechen</a>
  </p>
</form>
