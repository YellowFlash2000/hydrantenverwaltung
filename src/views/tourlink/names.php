<?php
/** @var array $tour */
/** @var string $names */
/** @var string[] $errors */
?>
<div class="card card-narrow">
  <h1><?= e($tour['name']) ?></h1>
  <?php if (($tour['beschreibung'] ?? '') !== ''): ?><p><?= nl2br(e((string) $tour['beschreibung'])) ?></p><?php endif; ?>
  <form method="post" action="<?= e(url('t/' . $tour['token'])) ?>">
    <?= csrf_field() ?>
    <?php if ($errors): ?><div class="alert alert-error" role="alert"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div><?php endif; ?>
    <div class="form-group">
      <label for="teilnehmer">Wer prüft heute? (Namen der Teilnehmer)</label>
      <input type="text" id="teilnehmer" name="teilnehmer" value="<?= e($names) ?>" maxlength="200" required autocomplete="name" placeholder="z. B. Anna Beispiel, Ben Muster">
      <p class="hint">Die Namen werden bei den Prüfungen dieser Tour gespeichert und bleiben auf diesem Gerät gemerkt, bis Sie sie ändern.</p>
    </div>
    <button type="submit" class="btn btn-primary btn-large">Weiter</button>
  </form>
</div>
