<?php
/** @var array $c */
/** @var array $cts */
/** @var array $progress */
/** @var array $tours */
/** @var array $counts */
/** @var int[] $selected */
/** @var array $stats */
$cid = (int) $c['id'];
$isActive = (int) $c['aktiv'] === 1;
?>
<p class="back"><a href="<?= e(url('pruefungen')) ?>">← Alle Prüfdurchgänge</a></p>
<div class="card-head">
  <div>
    <h1><?= e($c['name']) ?> <?= $isActive ? '<span class="badge badge-ok">aktiv</span>' : '<span class="badge">beendet</span>' ?></h1>
    <p class="hint"><?= e(format_dt($c['start_date'], false)) ?> – <?= $c['end_date'] === null ? 'offen' : e(format_dt($c['end_date'], false)) ?> · <?= (int) $stats['checked'] ?> Hydranten geprüft, <?= (int) $stats['defects'] ?> mit Mangel</p>
  </div>
  <p class="actions actions-top">
    <a class="btn btn-secondary" href="<?= e(url('maengel', ['filter' => 1, 'pruefung' => [$cid]])) ?>">Mängelübersicht</a>
  </p>
</div>

<div class="card">
  <h2>Touren in diesem Durchgang</h2>
  <?php if (!$cts): ?>
    <p class="hint">Diesem Durchgang sind noch keine Touren zugeordnet.</p>
  <?php else: ?>
    <?php if ($isActive): ?><p class="hint">Jede Tour hat einen eigenen Link. Wer ihn kennt, kann ohne Anmeldung die Revision eintragen. Der Link gilt, solange dieser Durchgang aktiv und der Link freigegeben ist.</p><?php endif; ?>
    <?php foreach ($cts as $ct):
      $tid = (int) $ct['id']; $p = $progress[$tid] ?? ['total' => 0, 'done' => 0, 'defects' => 0];
      $works = CampaignTour::linkWorks($ct); $link = CampaignTour::link($ct); $base = 'pruefungen/' . $cid . '/'; ?>
      <div class="tour-block">
        <h3><a href="<?= e(url('touren/' . $tid)) ?>"><?= e($ct['name']) ?></a></h3>
        <p><progress max="<?= max(1, (int) $p['total']) ?>" value="<?= (int) $p['done'] ?>"></progress>
          <strong><?= (int) $p['done'] ?> von <?= (int) $p['total'] ?></strong> geprüft<?= $p['defects'] > 0 ? ', davon ' . (int) $p['defects'] . ' mit Mangel' : '' ?></p>
        <?php if ($isActive): ?>
          <div class="copy-row">
            <input type="text" id="link-<?= (int) $ct['ct_id'] ?>" value="<?= e($link) ?>" readonly aria-label="Tour-Link <?= e($ct['name']) ?>">
            <button type="button" class="btn btn-secondary" data-copy="#link-<?= (int) $ct['ct_id'] ?>">Kopieren</button>
            <?php if ($works): ?><a class="btn btn-secondary" href="<?= e($link) ?>" target="_blank" rel="noopener noreferrer">Öffnen</a><?php endif; ?>
          </div>
          <p>Status: <?= $works ? '<span class="badge badge-ok">freigegeben</span>' : '<span class="badge badge-off">gesperrt</span>' ?></p>
          <div class="actions">
            <form method="post" action="<?= e(url($base . 'link/' . (int) $ct['ct_id'])) ?>" class="inline">
              <?= csrf_field() ?>
              <?php if ($works): ?>
                <button type="submit" name="aktion" value="sperren" class="btn btn-secondary btn-sm">Link sperren</button>
              <?php else: ?>
                <button type="submit" name="aktion" value="freigeben" class="btn btn-secondary btn-sm">Link freigeben</button>
              <?php endif; ?>
            </form>
            <form method="post" action="<?= e(url($base . 'link/' . (int) $ct['ct_id'])) ?>" class="inline" data-confirm="Einen neuen Link erzeugen? Der bisherige Link ist danach ungültig.">
              <?= csrf_field() ?><button type="submit" name="aktion" value="neu" class="btn btn-secondary btn-sm">Neuen Link erzeugen</button>
            </form>
          </div>
          <?php if ($works): ?>
            <details class="help">
              <summary>Link per E-Mail senden</summary>
              <form method="post" action="<?= e(url($base . 'mail/' . (int) $ct['ct_id'])) ?>">
                <?= csrf_field() ?>
                <div class="form-group">
                  <label for="adressen-<?= (int) $ct['ct_id'] ?>">E-Mail-Adressen (mehrere mit Komma, Leerzeichen oder Zeilenumbruch getrennt)</label>
                  <textarea id="adressen-<?= (int) $ct['ct_id'] ?>" name="adressen" rows="2" required placeholder="name@example.org"></textarea>
                </div>
                <div class="form-group">
                  <label for="text-<?= (int) $ct['ct_id'] ?>">Kurze Nachricht (optional)</label>
                  <textarea id="text-<?= (int) $ct['ct_id'] ?>" name="text" rows="2" maxlength="1000" placeholder="z. B. Treffpunkt Samstag 9 Uhr am Gerätehaus"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Senden</button>
              </form>
            </details>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php if ($isActive): ?>
<div class="card">
  <h2>Touren dieses Durchgangs ändern</h2>
  <?php if (!$tours): ?>
    <p class="hint">Es gibt noch keine Touren. <a href="<?= e(url('touren/neu')) ?>">Tour anlegen</a></p>
  <?php else: ?>
    <form method="post" action="<?= e(url('pruefungen/' . $cid . '/touren')) ?>">
      <?= csrf_field() ?>
      <fieldset class="form-group">
        <legend>Welche Touren werden geprüft?</legend>
        <label class="check"><input type="checkbox" data-check-all> Alle Touren</label>
        <?php foreach ($tours as $t): ?>
          <label class="check"><input type="checkbox" name="touren[]" value="<?= (int) $t['id'] ?>" data-check <?= in_array((int) $t['id'], $selected, true) ? 'checked' : '' ?>>
            <?= e($t['name']) ?> <span class="hint">(<?= (int) ($counts[(int) $t['id']] ?? 0) ?> Hydranten)</span></label>
        <?php endforeach; ?>
        <p class="hint">Neu hinzugefügte Touren bekommen einen eigenen Link. Eine Tour, für die in diesem Durchgang schon Revisionen vorliegen, bleibt dabei.</p>
      </fieldset>
      <button type="submit" class="btn btn-primary">Speichern</button>
    </form>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Prüfdurchgang beenden</h2>
  <p class="hint">Danach gelten die Tour-Links nicht mehr. Alle Ergebnisse bleiben erhalten.</p>
  <form method="post" action="<?= e(url('pruefungen/' . $cid . '/beenden')) ?>" data-confirm="Prüfdurchgang beenden? Die Tour-Links werden damit ungültig.">
    <?= csrf_field() ?><button type="submit" class="btn btn-secondary btn-sm">Beenden</button>
  </form>
</div>
<?php endif; ?>
