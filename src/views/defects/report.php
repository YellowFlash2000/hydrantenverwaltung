<?php
/** @var array $campaigns */
/** @var int[] $selected */
/** @var string $tour */
/** @var bool $withOk */
/** @var string[] $tourNames */
/** @var array $rows */
$query = ['filter' => 1, 'pruefung' => $selected, 'tour' => $tour, 'alle' => $withOk ? '1' : ''];
$nDefects = count(array_filter($rows, static fn ($r) => $r['ergebnis'] === Revision::DEFECT));
?>
<div class="card-head">
  <h1>Mängelübersicht</h1>
  <p class="actions actions-top"><a class="btn btn-secondary" href="<?= e(url('touren')) ?>">Touren</a></p>
</div>

<form method="get" action="<?= e(url('maengel')) ?>" class="card filter-bar">
  <input type="hidden" name="filter" value="1">
  <fieldset class="form-group">
    <legend>Prüfdurchgänge (mehrere möglich)</legend>
    <label class="check"><input type="checkbox" name="pruefung[]" value="<?= (int) DefectReport::NO_CAMPAIGN ?>" <?= in_array(DefectReport::NO_CAMPAIGN, $selected, true) ? 'checked' : '' ?>>
      Ohne Prüfdurchgang <span class="hint">(direkt am Hydranten erfasst)</span></label>
    <?php foreach ($campaigns as $c): ?>
      <label class="check"><input type="checkbox" name="pruefung[]" value="<?= (int) $c['id'] ?>" <?= in_array((int) $c['id'], $selected, true) ? 'checked' : '' ?>>
        <?= e($c['name']) ?> <span class="hint">(<?= e(format_dt($c['start_date'], false)) ?><?= (int) $c['aktiv'] === 1 ? ', aktiv' : '' ?>)</span></label>
    <?php endforeach; ?>
  </fieldset>
  <div class="form-row">
    <div class="form-group">
      <label for="tour">Nur Tour</label>
      <select id="tour" name="tour">
        <option value="">alle</option>
        <?php foreach ($tourNames as $n): ?><option value="<?= e($n) ?>" <?= $tour === $n ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label class="check"><input type="checkbox" name="alle" value="1" <?= $withOk ? 'checked' : '' ?>> auch Hydranten ohne Mangel</label>
    </div>
  </div>
  <div class="actions filter-actions">
    <button type="submit" class="btn btn-primary">Anzeigen</button>
    <?php if ($rows): ?><a class="btn btn-secondary" href="<?= e(url('maengel/export.csv', $query)) ?>">CSV-Export</a><?php endif; ?>
  </div>
</form>

<p class="hint result-count"><?= count($rows) ?> <?= count($rows) === 1 ? 'Eintrag' : 'Einträge' ?><?= $withOk ? ', davon ' . $nDefects . ' mit Mangel' : '' ?>. Je Hydrant und Prüfdurchgang zählt die letzte Prüfung. Mängel „ohne Prüfdurchgang“ gelten, bis der Hydrant später als in Ordnung geprüft wird, in der App oder in einem Durchgang.</p>

<?php if (!$rows): ?>
  <div class="card"><p><strong>Keine Einträge.</strong></p><p class="hint">Wählen Sie mindestens einen Prüfdurchgang oder „Ohne Prüfdurchgang“ aus.</p></div>
<?php else: ?>
  <div class="card card-flush">
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Prüfdurchgang</th><th>Tour</th><th>Kennung</th><th>Typ</th><th>Ergebnis</th><th>Mängel</th><th>Bemerkung</th><th>Geprüft</th><th>Fotos</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td data-label="Prüfdurchgang"><?= $r['campaign_name'] === null ? '<em>Ohne Prüfdurchgang</em>' : e($r['campaign_name']) ?></td>
            <td data-label="Tour"><?= $r['tour_name'] === null ? '–' : e($r['tour_name']) ?></td>
            <td data-label="Kennung"><a href="<?= e(url('hydranten/' . (int) $r['hydrant_id'])) ?>"><?= e($r['kennung']) ?></a></td>
            <td data-label="Typ"><?= e(Hydrant::typLabel($r['typ'])) ?></td>
            <td data-label="Ergebnis"><?= $r['ergebnis'] === Revision::DEFECT ? '<span class="badge badge-warn">Mangel</span>' : '<span class="badge badge-ok">in Ordnung</span>' ?></td>
            <td data-label="Mängel"><?= $r['defects'] ? e(implode(', ', $r['defects'])) : '–' ?></td>
            <td data-label="Bemerkung"><?= ($r['bemerkung'] ?? '') === '' ? '–' : nl2br(e((string) $r['bemerkung'])) ?></td>
            <td data-label="Geprüft"><?= e(format_dt($r['revised_at'])) ?><br><span class="hint"><?= e((string) ($r['quelle'] === 'tour' ? $r['teilnehmer'] : $r['user_name'])) ?></span></td>
            <td data-label="Fotos"><?= (int) $r['fotos'] ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
