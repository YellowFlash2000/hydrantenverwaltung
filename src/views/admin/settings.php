<?php
/** @var array $s */
/** @var string[] $errors */
/** @var string $open */
$action = url('admin/einstellungen');
$err = static function (string $section) use ($errors, $open): void {
    if ($errors && $open === $section) {
        echo '<div class="alert alert-error" role="alert">';
        foreach ($errors as $e) {
            echo '<div>' . e($e) . '</div>';
        }
        echo '</div>';
    }
};
$logoUrl = $s['logo_file'] !== '' ? url('branding/logo') . '?v=' . rawurlencode($s['logo_file']) : null;
?>
<h1>Einstellungen</h1>

<div class="card">
  <h2>Darstellung</h2>
  <?php $err('design'); ?>
  <form method="post" action="<?= e($action) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="section" value="design">
    <div class="form-group">
      <label for="app_name">Name der Anwendung</label>
      <input type="text" id="app_name" name="app_name" value="<?= e($s['app_name']) ?>" required maxlength="60">
    </div>
    <div class="form-row">
      <div class="form-group">
        <label for="color_primary">Hauptfarbe (Buttons, Akzente)</label>
        <input type="color" id="color_primary" name="color_primary" value="<?= e($s['color_primary']) ?>">
      </div>
      <div class="form-group">
        <label for="color_header">Farbe der Kopfzeile</label>
        <input type="color" id="color_header" name="color_header" value="<?= e($s['color_header']) ?>">
      </div>
      <div class="form-group">
        <label for="color_bg">Seitenhintergrund</label>
        <input type="color" id="color_bg" name="color_bg" value="<?= e($s['color_bg']) ?>">
      </div>
    </div>
    <div class="theme-preview" id="theme-preview" aria-hidden="true">
      <div class="theme-preview-head"><span id="pv-name"><?= e($s['app_name']) ?></span></div>
      <div class="theme-preview-body"><span class="btn btn-primary">Beispiel-Button</span> <span class="hint">Vorschau der Farben</span></div>
    </div>
    <div class="form-group">
      <label for="logo">Logo</label>
      <?php if ($logoUrl): ?>
        <div class="logo-preview"><img src="<?= e($logoUrl) ?>" alt="Aktuelles Logo"></div>
      <?php else: ?>
        <p class="hint">Aktuell wird das mitgelieferte Standard-Logo verwendet.</p>
      <?php endif; ?>
      <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp">
      <p class="hint">PNG, JPEG oder WebP, höchstens 3 MB. Am besten ein PNG mit transparentem Hintergrund; es wird auf höchstens 600 × 200 Pixel verkleinert.</p>
      <?php if ($logoUrl): ?>
        <label class="check"><input type="checkbox" name="logo_remove" value="1"> Logo entfernen und Standard-Logo verwenden</label>
      <?php endif; ?>
    </div>
    <button type="submit" class="btn btn-primary">Speichern</button>
  </form>
</div>

<div class="card">
  <h2>Felder</h2>
  <?php $err('felder'); ?>
  <form method="post" action="<?= e($action) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="section" value="felder">
    <div class="form-group">
      <label class="check">
        <input type="checkbox" name="show_pressure" value="1" <?= $s['show_pressure'] === '1' ? 'checked' : '' ?>>
        Feld „Druck“ bei Hydranten anzeigen und erfassen
      </label>
    </div>
    <div class="form-group">
      <label class="check">
        <input type="checkbox" name="show_flow" value="1" <?= $s['show_flow'] === '1' ? 'checked' : '' ?>>
        Feld „Durchfluss“ bei Hydranten anzeigen und erfassen
      </label>
    </div>
    <div class="form-group">
      <label class="check">
        <input type="checkbox" name="show_position" value="1" <?= $s['show_position'] === '1' ? 'checked' : '' ?>>
        Feld „Position“ bei Hydranten anzeigen und erfassen
      </label>
      <p class="hint">Ausgeblendete Felder erscheinen weder im Formular noch in Detailseite, Liste, Filter und Tour-Link. Bereits gespeicherte Werte bleiben erhalten; CSV- und OSM-Import/-Export behandeln weiterhin alle Felder.</p>
    </div>
    <button type="submit" class="btn btn-primary">Speichern</button>
  </form>
</div>

<div class="card" id="protokoll">
  <h2>Protokoll</h2>
  <?php $err('protokoll'); ?>
  <form method="post" action="<?= e($action) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="section" value="protokoll">
    <div class="form-group">
      <label for="audit_retention_months">Aufbewahrung</label>
      <select id="audit_retention_months" name="audit_retention_months">
        <?php foreach (AuditLog::RETENTION as $m => $label): ?>
          <option value="<?= (int) $m ?>" <?= (int) $s['audit_retention_months'] === $m ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <p class="hint">Einträge, die älter sind, werden einmal täglich automatisch gelöscht (beim nächsten Aufruf des Protokolls oder einer Anmeldung). „Unbegrenzt“ löscht nichts. Das Protokoll selbst sehen Sie unter <a href="<?= e(url('admin/protokoll')) ?>">Protokoll</a>.</p>
    </div>
    <button type="submit" class="btn btn-primary">Speichern</button>
  </form>
</div>

<div class="card">
  <h2>Stammdaten</h2>
  <p class="hint">Hydrantentypen mit Symbol, Positionen und der Mängelkatalog.</p>
  <p><a class="btn btn-secondary" href="<?= e(url('admin/stammdaten')) ?>">Stammdaten bearbeiten</a></p>
</div>

<div class="card">
  <h2>Karte</h2>
  <p class="hint">Mit der Kartenmitte starten die Auswahlkarte beim Anlegen eines Hydranten und die Übersichtskarte, solange noch keine Hydranten mit Koordinaten vorhanden sind. Klicken oder tippen Sie dazu einmal in die Karte (Zoomen allein setzt keine Mitte). Die Zoomstufe folgt dem Zoom der Karte.</p>
  <?php $err('karte'); ?>
  <form method="post" action="<?= e($action) ?>" data-confirm-empty-map data-lat-input="#map_lat" data-lon-input="#map_lon">
    <?= csrf_field() ?>
    <input type="hidden" name="section" value="karte">
    <div class="form-row">
      <div class="form-group">
        <label for="map_lat">Breite (lat)</label>
        <input type="text" inputmode="decimal" id="map_lat" name="map_lat" value="<?= e($s['map_lat']) ?>" placeholder="51.1234" autocomplete="off">
      </div>
      <div class="form-group">
        <label for="map_lon">Länge (lon)</label>
        <input type="text" inputmode="decimal" id="map_lon" name="map_lon" value="<?= e($s['map_lon']) ?>" placeholder="10.1234" autocomplete="off">
      </div>
      <div class="form-group">
        <label for="map_zoom">Zoomstufe (5–19)</label>
        <input type="number" id="map_zoom" name="map_zoom" value="<?= e($s['map_zoom']) ?>" min="5" max="19" class="input-short">
      </div>
    </div>
    <div class="map map-picker js-map" id="map" data-mode="picker" data-lat-input="#map_lat" data-lon-input="#map_lon" data-zoom-input="#map_zoom"
         tabindex="0" aria-label="Karte zur Auswahl der Kartenmitte"></div>
    <div class="actions map-tools">
      <button type="button" class="btn btn-secondary btn-sm" id="btn-locate">Meinen Standort verwenden</button>
      <button type="button" class="btn btn-secondary btn-sm" id="btn-clear">Kartenmitte entfernen</button>
    </div>
    <p class="hint" id="locate-status" role="status">Ohne Kartenmitte zeigt die Auswahlkarte die Mitte der vorhandenen Hydranten.</p>
    <button type="submit" class="btn btn-primary">Speichern</button>
  </form>
</div>

<div class="card">
  <h2>E-Mail-Versand</h2>
  <p class="hint">Für Einladungen, Passwort-Links und den Versand von Tour-Links. Ohne SMTP-Server wird die einfache PHP-Funktion mail() verwendet, die auf vielen Webspaces im Spam-Ordner landet. Empfohlen sind die SMTP-Daten eines Postfachs Ihrer Domain.</p>
  <?php $err('mail'); ?>
  <form method="post" action="<?= e($action) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="section" value="mail">
    <div class="form-row">
      <div class="form-group grow">
        <label for="mail_host">SMTP-Server</label>
        <input type="text" id="mail_host" name="mail_host" value="<?= e($s['mail_host']) ?>" placeholder="leer = PHP mail()" autocomplete="off">
      </div>
      <div class="form-group">
        <label for="mail_port">Port</label>
        <input type="number" id="mail_port" name="mail_port" value="<?= e($s['mail_port']) ?>" min="1" max="65535" class="input-short">
      </div>
      <div class="form-group">
        <label for="mail_secure">Verschlüsselung</label>
        <select id="mail_secure" name="mail_secure">
          <option value="tls" <?= $s['mail_secure'] === 'tls' ? 'selected' : '' ?>>STARTTLS (meist Port 587)</option>
          <option value="ssl" <?= $s['mail_secure'] === 'ssl' ? 'selected' : '' ?>>SSL/TLS (meist Port 465)</option>
          <option value="none" <?= $s['mail_secure'] === 'none' ? 'selected' : '' ?>>keine</option>
        </select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group grow">
        <label for="mail_user">Benutzername</label>
        <input type="text" id="mail_user" name="mail_user" value="<?= e($s['mail_user']) ?>" autocomplete="off">
      </div>
      <div class="form-group grow">
        <label for="mail_pass">Passwort</label>
        <input type="password" id="mail_pass" name="mail_pass" value="" autocomplete="new-password"
               placeholder="<?= $s['mail_pass_set'] === '1' ? 'gespeichert – leer lassen zum Beibehalten' : '' ?>">
        <?php if ($s['mail_pass_set'] === '1'): ?>
          <label class="check"><input type="checkbox" name="mail_pass_clear" value="1"> gespeichertes Passwort löschen</label>
        <?php endif; ?>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group grow">
        <label for="mail_from">Absenderadresse</label>
        <input type="email" id="mail_from" name="mail_from" value="<?= e($s['mail_from']) ?>" placeholder="z. B. hydranten@ihre-domain.de">
      </div>
      <div class="form-group grow">
        <label for="mail_from_name">Absendername</label>
        <input type="text" id="mail_from_name" name="mail_from_name" value="<?= e($s['mail_from_name']) ?>" placeholder="<?= e($s['app_name']) ?>">
      </div>
    </div>
    <button type="submit" class="btn btn-primary">Speichern</button>
  </form>
  <hr>
  <form method="post" action="<?= e(url('admin/einstellungen/testmail')) ?>">
    <?= csrf_field() ?>
    <p class="hint">Sendet eine Testnachricht mit den <strong>gespeicherten</strong> Einstellungen an Ihre eigene Adresse.</p>
    <button type="submit" class="btn btn-secondary">Testmail senden</button>
  </form>
</div>
