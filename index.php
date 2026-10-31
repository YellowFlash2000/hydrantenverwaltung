<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

// Wird vom Installer per JavaScript abgefragt, um zu prüfen, ob kurze Adressen (mod_rewrite) funktionieren.
if (Router::path() === '/rewrite-test') {
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo 'REWRITE_OK';
    exit;
}

if (!Config::installed()) {
    header('Location: ' . base_path() . '/install.php');
    exit;
}

// Datenbank-Update nach dem Hochladen einer neuen Programmversion
$migrationError = Migrator::run();
if ($migrationError !== null) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Datenbank-Update</title><body style="font-family:sans-serif;max-width:42em;margin:3em auto;padding:0 1em">'
        . '<h1>Das Datenbank-Update ist fehlgeschlagen</h1>'
        . '<p>Die neue Programmversion braucht eine Änderung an der Datenbank, die nicht ausgeführt werden konnte. Der Datenbank-Benutzer benötigt das Recht „ALTER“.</p>'
        . '<pre style="white-space:pre-wrap">' . htmlspecialchars($migrationError, ENT_QUOTES) . '</pre>'
        . '<p>Bitte beim Hoster das Recht prüfen oder die Datenbank vorübergehend mit einem Benutzer mit allen Rechten aktualisieren. Es sind keine Daten verloren gegangen.</p>';
    exit;
}

Router::dispatch(require SRC . '/routes.php');
