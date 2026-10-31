<?php
/** @var ?array $campaign */
/** @var array $tours */
/** @var array $progress */
/** @var array $inCampaign */
/** @var array $counts */
/** @var int $unassigned */
?>
<div class="card-head">
  <h1>Touren</h1>
  <p class="actions actions-top">
    <a class="btn btn-primary" href="<?= e(url('touren/neu')) ?>">Tour anlegen</a>
    <a class="btn btn-secondary" href="<?= e(url('pruefungen')) ?>">Prüfdurchgänge</a>
    <a class="btn btn-secondary" href="<?= e(url('maengel')) ?>">Mängelübersicht</a>
  </p>
</div>
<p class="hint">Touren sind feste Pläne: Sie bleiben bestehen, auch wenn ein Prüfdurchgang endet. Welche Touren geprüft werden, legen Sie im jeweiligen <a href="<?= e(url('pruefungen')) ?>">Prüfdurchgang</a> fest.</p>

<?php if ($campaign !== null): ?>
  <p class="hint">Aktiver Prüfdurchgang: <a href="<?= e(url('pruefungen/' . (int) $campaign['id'])) ?>"><strong><?= e($campaign['name']) ?></strong></a> (seit <?= e(format_dt($campaign['start_date'], false)) ?>)</p>
<?php else: ?>
  <p class="hint">Zurzeit ist kein Prüfdurchgang aktiv. <a href="<?= e(url('pruefungen/neu')) ?>">Prüfdurchgang anlegen</a></p>
<?php endif; ?>

<?php if (!$tours): ?>
  <div class="card"><p><strong>Es gibt noch keine Touren.</strong></p><p class="hint">Legen Sie eine Tour an und ordnen Sie ihr in der Hydrantenliste Hydranten zu.</p></div>
<?php else: ?>
  <div class="card card-flush">
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Tour</th><th>Hydranten</th><?php if ($campaign !== null): ?><th>Im aktiven Durchgang</th><?php endif; ?><th></th></tr></thead>
        <tbody>
        <?php foreach ($tours as $t): $tid = (int) $t['id']; $p = $progress[$tid] ?? ['total' => 0, 'done' => 0, 'defects' => 0]; ?>
          <tr>
            <td data-label="Tour"><a href="<?= e(url('touren/' . $tid)) ?>"><strong><?= e($t['name']) ?></strong></a>
              <?php if (($t['beschreibung'] ?? '') !== ''): ?><div class="hint"><?= e(mb_strimwidth((string) $t['beschreibung'], 0, 80, '…')) ?></div><?php endif; ?></td>
            <td data-label="Hydranten"><?= (int) ($counts[$tid] ?? 0) ?></td>
            <?php if ($campaign !== null): ?>
              <td data-label="Im aktiven Durchgang">
                <?php if (isset($inCampaign[$tid])): ?>
                  <progress max="<?= max(1, (int) $p['total']) ?>" value="<?= (int) $p['done'] ?>"></progress>
                  <?= (int) $p['done'] ?> von <?= (int) $p['total'] ?> geprüft<?= $p['defects'] > 0 ? ', ' . (int) $p['defects'] . ' Mängel' : '' ?>
                <?php else: ?><span class="hint">nicht dabei</span><?php endif; ?>
              </td>
            <?php endif; ?>
            <td class="cell-action"><a class="btn btn-secondary btn-sm" href="<?= e(url('touren/' . $tid)) ?>">Öffnen</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
<?php if ($unassigned > 0): ?>
  <p class="hint"><?= $unassigned ?> aktive Hydranten haben noch keine Tour. <a href="<?= e(url('hydranten', ['tour' => 'none'])) ?>">Anzeigen und zuweisen</a></p>
<?php endif; ?>
