<?php
/** @var array $form */
/** @var string[] $errors */
/** @var ?array $active */
/** @var array $tours */
/** @var array $counts */
?>
<h1>Prüfdurchgang anlegen</h1>
<?php if ($errors): ?>
  <div class="alert alert-error" role="alert"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
<?php endif; ?>
<?php if ($active !== null): ?>
  <div class="alert alert-warning" role="status">Der Prüfdurchgang „<?= e($active['name']) ?>“ ist noch aktiv. Er wird beim Anlegen beendet, seine Tour-Links werden ungültig.</div>
<?php endif; ?>

<form method="post" action="<?= e(url('pruefungen/neu')) ?>" class="card" data-confirm="<?= $active !== null ? 'Der aktive Prüfdurchgang wird beendet. Fortfahren?' : '' ?>">
  <?= csrf_field() ?>
  <div class="form-row">
    <div class="form-group grow">
      <label for="name">Name</label>
      <input type="text" id="name" name="name" value="<?= e($form['name']) ?>" maxlength="120" required>
    </div>
    <div class="form-group">
      <label for="start_date">Beginn</label>
      <input type="date" id="start_date" name="start_date" value="<?= e($form['start_date']) ?>" required>
    </div>
  </div>
  <fieldset class="form-group">
    <legend>Welche Touren werden in diesem Durchgang geprüft?</legend>
    <?php if (!$tours): ?>
      <p class="hint">Es gibt noch keine Touren. Sie können den Durchgang trotzdem anlegen und später Touren anlegen und hinzufügen.</p>
    <?php else: ?>
      <p class="hint">Die Auswahl entspricht dem letzten Durchgang. Eine große Jahresprüfung nimmt alle Touren, eine kleine Nachprüfung nur einzelne. Jede Tour bekommt einen eigenen, neuen Link.</p>
      <label class="check"><input type="checkbox" data-check-all> Alle Touren</label>
      <?php foreach ($tours as $t): ?>
        <label class="check"><input type="checkbox" name="touren[]" value="<?= (int) $t['id'] ?>" data-check <?= in_array((int) $t['id'], $form['touren'], true) ? 'checked' : '' ?>>
          <?= e($t['name']) ?> <span class="hint">(<?= (int) ($counts[(int) $t['id']] ?? 0) ?> Hydranten)</span></label>
      <?php endforeach; ?>
    <?php endif; ?>
  </fieldset>
  <p class="actions">
    <button type="submit" class="btn btn-primary">Prüfdurchgang anlegen</button>
    <a class="btn btn-secondary" href="<?= e(url('pruefungen')) ?>">Abbrechen</a>
  </p>
</form>
