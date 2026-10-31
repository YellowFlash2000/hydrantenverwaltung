<?php
/** @var array $defects */
/** @var string[] $defectErrors */
$errors = $defectErrors;
?>
<h2>Mängelkatalog</h2>
<p class="hint">Diese Mängel stehen bei einer Revision zur Auswahl, wenn ein Hydrant „Mangel“ erhält. Je Mangel legen Sie fest, für welche Hydrantentypen er gilt. Mängel werden nie gelöscht, sondern nur ausgeblendet, damit frühere Revisionen vollständig bleiben.</p>

<?php if ($errors): ?>
  <div class="alert alert-error" role="alert"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<form method="post" action="<?= e(url('admin/maengel')) ?>">
  <?= csrf_field() ?>
  <div class="card">
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr><th>Reihenfolge</th><th>Mangel</th><?php foreach (HydrantType::labels() as $label): ?><th><?= e($label) ?></th><?php endforeach; ?><th>Aktiv</th></tr>
        </thead>
        <tbody>
        <?php foreach ($defects as $d): $id = (int) $d['id']; $types = DefectType::types($d); ?>
          <tr class="<?= (int) $d['aktiv'] === 1 ? '' : 'row-inactive' ?>">
            <td data-label="Reihenfolge"><input type="text" inputmode="numeric" name="sort[<?= $id ?>]" value="<?= e((string) $d['sortierung']) ?>" class="input-short" aria-label="Reihenfolge"></td>
            <td data-label="Mangel"><input type="text" name="name[<?= $id ?>]" value="<?= e((string) $d['name']) ?>" maxlength="120" aria-label="Name des Mangels"></td>
            <?php foreach (HydrantType::labels() as $k => $label): ?>
              <td data-label="<?= e($label) ?>"><input type="checkbox" name="typ[<?= $id ?>][]" value="<?= e($k) ?>" <?= in_array($k, $types, true) ? 'checked' : '' ?> aria-label="<?= e($label) ?>"></td>
            <?php endforeach; ?>
            <td data-label="Aktiv"><input type="checkbox" name="aktiv[<?= $id ?>]" value="1" <?= (int) $d['aktiv'] === 1 ? 'checked' : '' ?> aria-label="Aktiv"></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <h2>Neuen Mangel hinzufügen</h2>
    <div class="form-group">
      <label for="neu_name">Bezeichnung</label>
      <input type="text" id="neu_name" name="neu_name" maxlength="120" autocomplete="off">
    </div>
    <div class="form-group">
      <span class="label">Gilt für</span>
      <?php foreach (HydrantType::labels() as $k => $label): ?>
        <label class="check"><input type="checkbox" name="neu_typ[]" value="<?= e($k) ?>" checked> <?= e($label) ?></label>
      <?php endforeach; ?>
    </div>
  </div>

  <p class="actions">
    <button type="submit" class="btn btn-primary">Mängelkatalog speichern</button>
  </p>
</form>
