<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

header('Content-Type: text/html; charset=utf-8');

$detectedBase = (is_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . base_path();

function page_head(string $title): void
{
    echo '<!doctype html><html lang="de"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">'
        . '<title>' . e($title) . '</title>'
        . '<link rel="stylesheet" href="' . e(asset('css/app.css')) . '">'
        . '<script src="' . e(asset('js/install.js')) . '" defer></script></head><body class="installer">'
        . '<header class="site-header"><div class="container header-inner"><span class="brand">'
        . '<img src="' . e(asset('img/logo-default.svg')) . '" alt="" class="brand-logo"><span class="brand-name">Hydrantenverwaltung – Installation</span>'
        . '</span></div></header><main class="container">';
}

function page_foot(): void
{
    echo '</main></body></html>';
}

// ---- Bereits installiert ----------------------------------------------------
if (Config::installed()) {
    page_head('Bereits installiert');
    echo '<div class="card card-narrow"><h1>Bereits installiert</h1>'
        . '<p>Die Anwendung ist eingerichtet. Bitte löschen Sie die Datei <code>install.php</code> vom Webspace.</p>'
        . '<p><a class="btn btn-primary" href="' . e(url('login')) . '">Zur Anmeldung</a></p></div>';
    page_foot();
    exit;
}

$errors = [];
$in = [
    'db_host' => 'localhost', 'db_port' => '3306', 'db_name' => '', 'db_user' => '', 'db_prefix' => 'hyd_', 'db_mode' => 'new',
    'app_name' => 'Hydrantenverwaltung', 'admin_name' => '', 'admin_email' => '',
    'base_url' => $detectedBase, 'pretty_urls' => '1',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_valid()) {
        $errors[] = 'Das Formular ist abgelaufen. Bitte versuchen Sie es erneut.';
    } else {
        foreach ($_POST as $k => $v) {
            if (is_string($v) && $k !== '_csrf') {
                $in[$k] = $v;
            }
        }
        $in['pretty_urls'] = isset($_POST['pretty_urls']) ? '1' : '';
        $errors = Installer::run($in);

        if (!$errors) {
            Config::load(); // frisch geschriebene Konfiguration, damit url() die gewählte Adressform nutzt
            $deleted = @unlink(__FILE__);
            page_head('Installation abgeschlossen');
            echo '<div class="card card-narrow"><h1>Installation abgeschlossen</h1>'
                . '<p>Die Datenbank wurde eingerichtet und der Administrator angelegt.</p>';
            if ($deleted) {
                echo '<div class="alert alert-success">Die Installationsdatei <code>install.php</code> wurde automatisch gelöscht.</div>';
            } else {
                echo '<div class="alert alert-warning"><strong>Wichtig:</strong> Bitte löschen Sie jetzt die Datei <code>install.php</code> '
                    . 'per FTP vom Webspace. Solange sie dort liegt, ist die Installation zwar gesperrt, die Datei wird aber nicht mehr gebraucht.</div>';
            }
            echo '<p><a class="btn btn-primary" href="' . e(url('login')) . '">Zur Anmeldung</a></p></div>';
            page_foot();
            exit;
        }
    }
}

page_head('Installation');
$reqOk = Installer::requirementsOk();
?>
<div class="card">
  <h1>Installation</h1>
  <p>Diese Seite richtet die Datenbank ein und legt den ersten Administrator an. Bitte legen Sie vorher beim Hoster eine MySQL-Datenbank samt Benutzer an. Es kann auch eine bereits vorhandene Datenbank sein, die dann zusätzliche Tabellen mit Präfix erhält.</p>

  <h2>1. Voraussetzungen</h2>
  <ul class="checklist">
    <?php foreach (Installer::requirements() as $r): ?>
      <li class="<?= $r['ok'] ? 'ok' : ($r['required'] ? 'bad' : 'warn') ?>">
        <span class="mark"><?= $r['ok'] ? '✔' : ($r['required'] ? '✘' : '!') ?></span>
        <?= e($r['label']) ?>
        <?php if (!$r['ok'] && $r['note'] !== ''): ?><span class="hint"> – <?= e($r['note']) ?></span><?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>

  <?php if ($errors): ?>
    <div class="alert alert-error" role="alert">
      <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="post" action="">
    <?= csrf_field() ?>

    <h2>2. Datenbank</h2>
    <div class="form-row">
      <div class="form-group grow">
        <label for="db_host">Datenbank-Server</label>
        <input type="text" id="db_host" name="db_host" value="<?= e($in['db_host']) ?>" required>
      </div>
      <div class="form-group">
        <label for="db_port">Port</label>
        <input type="number" id="db_port" name="db_port" value="<?= e($in['db_port']) ?>" class="input-short">
      </div>
    </div>
    <div class="form-group">
      <label for="db_name">Datenbankname</label>
      <input type="text" id="db_name" name="db_name" value="<?= e($in['db_name']) ?>" required autocomplete="off">
    </div>
    <fieldset class="form-group db-mode">
      <legend>Wie soll die Datenbank verwendet werden?</legend>
      <label class="check">
        <input type="radio" name="db_mode" value="new" <?= $in['db_mode'] === 'new' ? 'checked' : '' ?>>
        <span><strong>Neue, leere Datenbank</strong><br><span class="hint">Die Datenbank wird nur von dieser Anwendung benutzt. Die Tabellen heißen z. B. <code>users</code> und <code>hydrants</code>.</span></span>
      </label>
      <label class="check">
        <input type="radio" name="db_mode" value="existing" <?= $in['db_mode'] === 'existing' ? 'checked' : '' ?>>
        <span><strong>Bestehende Datenbank mitbenutzen</strong><br><span class="hint">Die Tabellen dieser Anwendung werden zusätzlich angelegt und beginnen mit einem Präfix (z. B. <code>hyd_users</code>). Vorhandene Tabellen und Daten werden nicht verändert.</span></span>
      </label>
      <div class="form-group prefix-group" id="prefix-group">
        <label for="db_prefix">Tabellen-Präfix</label>
        <input type="text" id="db_prefix" name="db_prefix" value="<?= e($in['db_prefix']) ?>" maxlength="20" class="input-short" autocomplete="off">
        <p class="hint">Nur Buchstaben, Ziffern und Unterstrich. Der Installer bricht ab, falls mit diesem Präfix schon Tabellen existieren.</p>
      </div>
    </fieldset>
    <div class="form-row">
      <div class="form-group grow">
        <label for="db_user">Datenbank-Benutzer</label>
        <input type="text" id="db_user" name="db_user" value="<?= e($in['db_user']) ?>" required autocomplete="off">
      </div>
      <div class="form-group grow">
        <label for="db_pass">Datenbank-Passwort</label>
        <input type="password" id="db_pass" name="db_pass" value="" autocomplete="new-password">
      </div>
    </div>

    <h2>3. Anwendung</h2>
    <div class="form-group">
      <label for="app_name">Name der Anwendung</label>
      <input type="text" id="app_name" name="app_name" value="<?= e($in['app_name']) ?>" required maxlength="60">
      <p class="hint">Farben und Logo können später unter „Einstellungen“ angepasst werden.</p>
    </div>
    <div class="form-group">
      <label for="base_url">Basis-URL</label>
      <input type="url" id="base_url" name="base_url" value="<?= e($in['base_url']) ?>" required>
      <p class="hint">Adresse des Ordners, in dem die Anwendung liegt, ohne abschließenden Schrägstrich. Sie wird für Links in E-Mails verwendet – bitte prüfen (möglichst mit https://).</p>
    </div>
    <div class="form-group">
      <label class="check">
        <input type="checkbox" name="pretty_urls" value="1" <?= $in['pretty_urls'] === '1' ? 'checked' : '' ?>>
        Kurze Adressen verwenden (benötigt mod_rewrite, bei den meisten Webspaces vorhanden)
      </label>
      <p class="hint" id="rewrite-note">Die Seite prüft automatisch, ob das auf diesem Webspace funktioniert. Falls die Seiten nach der Installation „nicht gefunden“ melden, in <code>storage/config.php</code> den Eintrag <code>'pretty_urls'</code> auf <code>false</code> setzen.</p>
    </div>

    <h2>4. Administrator</h2>
    <div class="form-group">
      <label for="admin_name">Name</label>
      <input type="text" id="admin_name" name="admin_name" value="<?= e($in['admin_name']) ?>" required maxlength="120">
    </div>
    <div class="form-group">
      <label for="admin_email">E-Mail-Adresse (Login)</label>
      <input type="email" id="admin_email" name="admin_email" value="<?= e($in['admin_email']) ?>" required>
    </div>
    <div class="form-row">
      <div class="form-group grow">
        <label for="admin_pass">Passwort</label>
        <input type="password" id="admin_pass" name="admin_pass" required minlength="10" autocomplete="new-password">
        <p class="hint">Mindestens 10 Zeichen.</p>
      </div>
      <div class="form-group grow">
        <label for="admin_pass2">Passwort wiederholen</label>
        <input type="password" id="admin_pass2" name="admin_pass2" required minlength="10" autocomplete="new-password">
      </div>
    </div>

    <button type="submit" class="btn btn-primary" <?= $reqOk ? '' : 'disabled' ?>>Installieren</button>
    <?php if (!$reqOk): ?><p class="hint">Bitte zuerst die mit ✘ markierten Voraussetzungen erfüllen.</p><?php endif; ?>
  </form>
</div>
<?php page_foot();
