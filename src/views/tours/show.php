<?php
/** @var array $t */
/** @var ?array $campaign */
/** @var ?array $ct */
/** @var array $hydrants */
/** @var int $done */
/** @var int $defects */
/** @var array $outings */
/** @var bool $works */
/** @var string $link */
/** @var int $revCount */
$tid = (int) $t['id'];
$active = $ct !== null;
$total = count($hydrants);
?>
<p class="back"><a href="<?= e(url('touren')) ?>">← Alle Touren</a></p>
<div class="card-head">
  <h1><?= e($t['name']) ?></h1>
  <p class="actions actions-top">
    <a class="btn btn-secondary" href="<?= e(url('touren/' . $tid . '/bearbeiten')) ?>">Umbenennen</a>
  </p>
</div>
<?php if (($t['beschreibung'] ?? '') !== ''): ?><p><?= nl2br(e((string) $t['beschreibung'])) ?></p><?php endif; ?>

<div class="card">
  <h2>Prüfdurchgang und Tour-Link</h2>
  <?php if ($campaign === null): ?>
    <p class="hint">Zurzeit ist kein Prüfdurchgang aktiv. Der Tour-Link entsteht, wenn die Tour in einem Prüfdurchgang geprüft wird.</p>
  <?php elseif ($ct === null): ?>
    <p class="hint">Diese Tour gehört nicht zum aktiven Prüfdurchgang „<?= e($campaign['name']) ?>“. <a href="<?= e(url('pruefungen/' . (int) $campaign['id'])) ?>">Touren des Durchgangs ändern</a></p>
  <?php else: ?>
    <p class="hint">Diese Tour gehört zum aktiven Prüfdurchgang <strong><?= e($campaign['name']) ?></strong>. Wer den Link kennt, kann ohne Anmeldung die Revision eintragen. Sperren, erneuern und per E-Mail senden können Sie in der <a href="<?= e(url('pruefungen/' . (int) $campaign['id'])) ?>">Verwaltung des Prüfdurchgangs</a>.</p>
    <div class="copy-row">
      <input type="text" id="tour-link" value="<?= e($link) ?>" readonly aria-label="Tour-Link">
      <button type="button" class="btn btn-secondary" data-copy="#tour-link">Kopieren</button>
      <?php if ($works): ?><a class="btn btn-secondary" href="<?= e($link) ?>" target="_blank" rel="noopener noreferrer">Öffnen</a><?php endif; ?>
    </div>
    <p>Status: <?= $works ? '<span class="badge badge-ok">freigegeben</span>' : '<span class="badge badge-off">gesperrt</span>' ?></p>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Hydranten dieser Tour</h2>
  <?php if ($ct !== null): ?>
    <p><progress max="<?= max(1, $total) ?>" value="<?= $done ?>"></progress> <strong><?= $done ?> von <?= $total ?></strong> geprüft<?= $defects > 0 ? ', davon ' . $defects . ' mit Mangel' : '' ?>.</p>
  <?php endif; ?>
  <?php if (!$hydrants): ?>
    <p class="hint">Dieser Tour sind noch keine aktiven Hydranten zugeordnet. Wählen Sie in der <a href="<?= e(url('hydranten')) ?>">Hydrantenliste</a> Hydranten aus und weisen Sie sie dieser Tour zu.</p>
  <?php else: ?>
    <form method="post" action="<?= e(url('hydranten/zuweisen')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="back_tour" value="<?= $tid ?>">
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th><input type="checkbox" data-check-all aria-label="Alle auswählen"></th><th>Kennung</th><th>Typ</th><?php if ($active): ?><th>Prüfung</th><th>Datum</th><?php endif; ?></tr></thead>
          <tbody>
          <?php foreach ($hydrants as $h): ?>
            <tr>
              <td data-label=""><input type="checkbox" name="ids[]" value="<?= (int) $h['id'] ?>" data-check aria-label="<?= e($h['kennung']) ?> auswählen"></td>
              <td data-label="Kennung"><a href="<?= e(url('hydranten/' . (int) $h['id'])) ?>"><?= e($h['kennung']) ?></a></td>
              <td data-label="Typ"><?= HydrantType::icon($h['typ']) ?> <?= e(Hydrant::typLabel($h['typ'])) ?></td>
              <?php if ($active): ?>
              <td data-label="Prüfung"><?php
                if ($h['rev_ergebnis'] === Revision::DEFECT) { echo '<span class="badge badge-warn">Mangel</span>'; }
                elseif ($h['rev_ergebnis'] === Revision::OK) { echo '<span class="badge badge-ok">in Ordnung</span>'; }
                else { echo '<span class="badge">offen</span>'; } ?></td>
              <td data-label="Datum"><?= $h['rev_at'] === null ? '–' : e(format_dt($h['rev_at'])) ?></td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="actions"><button type="submit" name="aktion" value="entfernen" class="btn btn-secondary btn-sm">Ausgewählte aus der Tour nehmen</button></p>
    </form>
  <?php endif; ?>
</div>

<?php if ($outings): ?>
<div class="card">
  <h2>Teilnehmer bisher (alle Prüfdurchgänge)</h2>
  <ul class="plain">
    <?php foreach ($outings as $o): ?><li><?= e(format_dt($o['started_at'])) ?> · <?= e($o['teilnehmer']) ?></li><?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<?php if ($revCount === 0): ?>
<div class="card">
  <h2>Tour löschen</h2>
  <p class="hint">Möglich, solange es noch keine Revisionen zu dieser Tour gibt. Die Hydranten bleiben erhalten und haben danach keine Tour; die Tour verschwindet auch aus den Prüfdurchgängen.</p>
  <form method="post" action="<?= e(url('touren/' . $tid . '/loeschen')) ?>" data-confirm="Tour wirklich löschen?">
    <?= csrf_field() ?><button type="submit" class="btn btn-secondary btn-sm">Tour löschen</button>
  </form>
</div>
<?php endif; ?>
