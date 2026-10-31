<?php
/** @var array $campaigns */
/** @var array $stats */
/** @var ?array $active */
?>
<div class="card-head">
  <h1>Prüfdurchgänge</h1>
  <a class="btn btn-primary" href="<?= e(url('pruefungen/neu')) ?>">Neuen Prüfdurchgang anlegen</a>
</div>
<p class="hint">Ein Prüfdurchgang legt fest, welche Touren in einem Zeitraum geprüft werden: eine große Jahresprüfung mit allen Touren oder kleine Prüfungen mit wenigen. Es ist immer nur einer aktiv; nur für diesen gelten die Tour-Links. Beendete Durchgänge bleiben mit allen Ergebnissen erhalten.</p>

<?php if (!$campaigns): ?>
  <div class="card"><p><strong>Es gibt noch keinen Prüfdurchgang.</strong></p><p class="hint">Legen Sie den ersten an, um Touren einzurichten.</p></div>
<?php else: ?>
<div class="card card-flush">
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Name</th><th>Zeitraum</th><th>Status</th><th>Geprüft</th><th>Mängel</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($campaigns as $c): $id = (int) $c['id']; $s = $stats[$id]; ?>
        <tr>
          <td data-label="Name"><a href="<?= e(url('pruefungen/' . $id)) ?>"><strong><?= e($c['name']) ?></strong></a></td>
          <td data-label="Zeitraum"><?= e(format_dt($c['start_date'], false)) ?> – <?= $c['end_date'] === null ? 'offen' : e(format_dt($c['end_date'], false)) ?></td>
          <td data-label="Status"><?= (int) $c['aktiv'] === 1 ? '<span class="badge badge-ok">aktiv</span>' : '<span class="badge">beendet</span>' ?></td>
          <td data-label="Geprüft"><?= (int) $s['checked'] ?> Hydranten</td>
          <td data-label="Mängel"><?= (int) $s['defects'] ?></td>
          <td class="cell-action">
            <a class="btn btn-secondary btn-sm" href="<?= e(url('pruefungen/' . $id)) ?>">Öffnen</a>
            <a class="btn btn-secondary btn-sm" href="<?= e(url('maengel', ['filter' => 1, 'pruefung' => [$id]])) ?>">Mängel</a>
            <?php if ((int) $c['aktiv'] === 1): ?>
              <form method="post" action="<?= e(url('pruefungen/' . $id . '/beenden')) ?>" class="inline" data-confirm="Prüfdurchgang beenden? Die Tour-Links werden damit ungültig.">
                <?= csrf_field() ?><button type="submit" class="btn btn-secondary btn-sm">Beenden</button>
              </form>
            <?php elseif ($active === null): ?>
              <form method="post" action="<?= e(url('pruefungen/' . $id . '/oeffnen')) ?>" class="inline">
                <?= csrf_field() ?><button type="submit" class="btn btn-secondary btn-sm">Wieder öffnen</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
