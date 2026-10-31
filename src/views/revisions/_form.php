<?php
/**
 * Formular für eine Revision (in der Anwendung und über den Tour-Link).
 * @var array  $hydrant
 * @var array  $defectList  zur Auswahl stehende Mängel
 * @var string $revAction   Ziel des Formulars
 * @var array  $revForm     bisherige Eingaben ['ergebnis', 'maengel' => [], 'bemerkung']
 * @var string[] $revErrors
 * @var string $revButton   Beschriftung des Speichern-Knopfes
 */
$sel = array_map('intval', is_array($revForm['maengel'] ?? null) ? $revForm['maengel'] : []);
$res = (string) ($revForm['ergebnis'] ?? '');
?>
<form method="post" action="<?= e($revAction) ?>" enctype="multipart/form-data" class="revision-form" id="revision-form">
  <?= csrf_field() ?>
  <?php if ($revErrors): ?>
    <div class="alert alert-error" role="alert"><?php foreach ($revErrors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
  <?php endif; ?>
  <fieldset class="form-group result-choice">
    <legend>Ergebnis</legend>
    <label class="choice choice-ok"><input type="radio" name="ergebnis" value="<?= Revision::OK ?>" <?= $res === Revision::OK ? 'checked' : '' ?> required> <span>In Ordnung</span></label>
    <label class="choice choice-defect"><input type="radio" name="ergebnis" value="<?= Revision::DEFECT ?>" <?= $res === Revision::DEFECT ? 'checked' : '' ?>> <span>Mangel</span></label>
  </fieldset>

  <div class="defect-section" data-defect-section <?= $res === Revision::DEFECT ? '' : 'hidden' ?>>
    <fieldset class="form-group">
      <legend>Welche Mängel? (mehrere möglich)</legend>
      <?php foreach ($defectList as $d): ?>
        <label class="check"><input type="checkbox" name="maengel[]" value="<?= (int) $d['id'] ?>" <?= in_array((int) $d['id'], $sel, true) ? 'checked' : '' ?>> <?= e($d['name']) ?></label>
      <?php endforeach; ?>
    </fieldset>
    <div class="form-group">
      <label for="rev_fotos">Fotos vom Mangel (bis zu <?= Revision::MAX_PHOTOS ?>)</label>
      <input type="file" id="rev_fotos" name="fotos[]" accept="image/jpeg,image/png,image/webp" capture="environment" multiple data-resize>
      <p class="hint">Fotos sind nur bei einem Mangel möglich.</p>
    </div>
  </div>

  <div class="form-group">
    <label for="rev_bemerkung">Bemerkung (optional)</label>
    <textarea id="rev_bemerkung" name="bemerkung" rows="3" maxlength="2000"><?= e((string) ($revForm['bemerkung'] ?? '')) ?></textarea>
  </div>
  <button type="submit" class="btn btn-primary btn-large"><?= e($revButton) ?></button>
</form>
