<?php
/** @var int $files */
/** @var bool $writable */
/** @var int $hydrants */
?>
<h1>Import und Export</h1>

<div class="card">
  <h2>Hydranten importieren</h2>
  <p class="hint">CSV-Datei mit den Stammdaten. Vor dem Import sehen Sie eine Vorschau, es wird erst nach Ihrer Bestätigung etwas gespeichert.</p>
  <form method="post" action="<?= e(url('admin/import/hydranten')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="form-group">
      <label for="datei">CSV-Datei</label>
      <input type="file" id="datei" name="datei" accept=".csv,.txt,text/csv" required>
    </div>
    <fieldset class="form-group">
      <legend>Wenn die Kennung schon vorhanden ist</legend>
      <label class="check"><input type="radio" name="default" value="ask" checked> Bei jedem Hydranten einzeln nachfragen</label>
      <label class="check"><input type="radio" name="default" value="update"> Alle aktualisieren (mit Vorschau, Änderungen einzeln änderbar)</label>
      <label class="check"><input type="radio" name="default" value="new"> Alle als neue Hydranten anlegen (neue Kennung je Hydrant eingeben)</label>
      <label class="check"><input type="radio" name="default" value="ignore"> Alle ignorieren</label>
    </fieldset>
    <button type="submit" class="btn btn-primary">Datei prüfen</button>
  </form>
  <details class="help">
    <summary>Aufbau der Datei</summary>
    <ul>
      <li>Trennzeichen Semikolon (Komma oder Tabulator werden ebenfalls erkannt), Zeichensatz UTF-8 oder Windows (Excel). Die erste Zeile enthält die Spaltennamen.</li>
      <li>Spalten: <code>kennung</code> und <code>typ</code> sind Pflicht. Optional: <code>durchmesser_dn</code>, <code>durchfluss_lmin</code>, <code>druck_bar</code>, <code>position</code>, <code>tour</code>, <code>lat</code>, <code>lon</code>, <code>bemerkung</code>, <code>aktiv</code>. Die Reihenfolge ist egal, fehlende Spalten werden nicht verändert.</li>
      <li>Typ: einer der unter „Stammdaten“ gepflegten Typen (z. B. Unterflur, Überflur, Saugbrunnen). Durchfluss: Zahl, auch mit „&lt;“ oder „&gt;“ davor (z. B. <code>&lt;400</code>). Position: eine der unter „Stammdaten“ gepflegten Positionen oder leer. Aktiv: ja oder nein.</li>
      <li>HTML-Schreibweisen wie <code>&amp;uuml;</code> oder <code>&amp;szlig;</code> werden in lesbare Zeichen umgewandelt. Angaben wie „3200 l/min“ oder „DN 100“ werden auf die Zahl gekürzt.</li>
      <li>Bei vorhandenen Hydranten, die aktualisiert werden, löscht eine leere Zelle den bisherigen Wert dieses Feldes. Spalten, die in der Datei fehlen, bleiben unberührt.</li>
      <li>Fehlerhafte Zeilen werden übersprungen und in der Vorschau mit Grund aufgelistet. Die Spalte „tour“ (Name) ordnet den Hydranten einer Tour zu; eine leere Zelle nimmt ihn aus der Tour, eine unbekannte Tour wird angelegt.</li>
    </ul>
    <p><a href="<?= e(url('admin/import/muster-hydranten.csv')) ?>">Mustervorlage herunterladen</a></p>
  </details>
</div>

<div class="card">
  <h2>Hydranten aus OpenStreetMap</h2>
  <p class="hint">Alle Hydranten einer Gemeinde aus OpenStreetMap als Grundlage übernehmen (Koordinaten, wenn vorhanden Typ, Durchmesser und Position). Das Ergebnis geht durch dieselbe Importvorschau.</p>
  <p><a class="btn btn-secondary" href="<?= e(url('admin/osm')) ?>">Aus OpenStreetMap holen</a></p>
</div>

<div class="card">
  <h2>Bilder importieren</h2>
  <p class="hint">Unabhängig vom Hydranten-Import und beliebig oft möglich. So geht es:</p>
  <ol>
    <li>Die Bilddateien (JPG, PNG oder WebP) per FTP in den Ordner <code>storage/import/</code> der Anwendung hochladen.</li>
    <li>Eine Zuordnungs-CSV mit den Spalten <code>kennung</code> (wie beim Hydranten), <code>datei</code> (Dateiname) und optional <code>beschriftung</code> hochladen.</li>
    <li>Nach der Vorschau werden die Bilder übernommen und aus dem Importordner entfernt. Dateien, die sich nicht zuordnen oder verarbeiten lassen, bleiben dort liegen.</li>
  </ol>
  <p>Im Importordner liegen aktuell <strong><?= (int) $files ?></strong> Dateien.
    <?php if (!$writable): ?><span class="badge badge-off">Ordner nicht beschreibbar</span><br><span class="hint">Die Anwendung kann den Ordner storage/import/ nicht ändern. Verarbeitete Dateien ließen sich nicht entfernen. Bitte die Schreibrechte des Ordners prüfen.</span><?php endif; ?>
  </p>
  <form method="post" action="<?= e(url('admin/import/bilder')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="form-group">
      <label for="datei_bilder">Zuordnungs-CSV</label>
      <input type="file" id="datei_bilder" name="datei" accept=".csv,.txt,text/csv" required>
    </div>
    <button type="submit" class="btn btn-primary">Zuordnung prüfen</button>
    <a class="btn btn-secondary" href="<?= e(url('admin/import/muster-bilder.csv')) ?>">Mustervorlage</a>
  </form>
</div>

<div class="card">
  <h2>Export</h2>
  <p>Alle <?= (int) $hydrants ?> Hydranten (auch deaktivierte) als CSV in demselben Format, das der Import liest. Geeignet zur Datensicherung und zum Bearbeiten in Excel mit anschließendem Import. Bilder sind nicht enthalten.</p>
  <p><a class="btn btn-secondary" href="<?= e(url('admin/export/hydranten.csv')) ?>">Hydranten als CSV herunterladen</a></p>
  <h3>Export für OpenStreetMap</h3>
  <p>Hydranten mit den Schlüsseln, die OpenStreetMap verwendet (CSV oder OSM-XML für JOSM). Exportiert wird die Hydrantenliste mit ihrem aktuellen Filter; hier alle aktiven Hydranten.</p>
  <p><a class="btn btn-secondary" href="<?= e(url('admin/export/osm')) ?>">Für OpenStreetMap exportieren</a></p>
</div>
