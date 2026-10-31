<?php
declare(strict_types=1);

define('SRC', __DIR__);
define('APP_ROOT', dirname(__DIR__));
define('STORAGE', APP_ROOT . '/storage');

spl_autoload_register(static function (string $class): void {
    foreach (['lib', 'controllers'] as $dir) {
        $file = SRC . '/' . $dir . '/' . $class . '.php';
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});

require SRC . '/lib/helpers.php';

Config::load();
date_default_timezone_set((string) Config::get('timezone', 'Europe/Berlin'));
mb_internal_encoding('UTF-8');

// ---- HTTPS erzwingen --------------------------------------------------------
// Gesteuert über die Konfiguration (vom Installer gesetzt). Das Ziel stammt aus der Basis-URL,
// nicht aus dem Host-Header der Anfrage.
if (PHP_SAPI !== 'cli' && Config::get('force_https') && !is_https()) {
    $host = (string) parse_url((string) Config::get('base_url', ''), PHP_URL_HOST);
    $port = parse_url((string) Config::get('base_url', ''), PHP_URL_PORT);
    if ($host !== '') {
        header('Location: https://' . $host . ($port ? ':' . $port : '') . (string) ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
        exit;
    }
}

// ---- Fehlerbehandlung -------------------------------------------------------
ini_set('display_errors', '0');
set_exception_handler(static function (\Throwable $e): void {
    error_log('Hydrantenverwaltung: ' . $e::class . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    $detail = Config::get('debug') ? '<pre>' . e($e::class . ': ' . $e->getMessage()) . '</pre>' : '';
    echo '<!doctype html><meta charset="utf-8"><title>Fehler</title>'
        . '<body style="font-family:sans-serif;max-width:40em;margin:3em auto;padding:0 1em">'
        . '<h1>Es ist ein Fehler aufgetreten</h1><p>Bitte versuchen Sie es später erneut. '
        . 'Wenn das Problem bleibt, informieren Sie den Administrator.</p>' . $detail;
});

// ---- Sicherheits-Header -----------------------------------------------------
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    // Kartenkacheln von openstreetmap.org verlangen laut Nutzungsrichtlinie einen Referer. Bei fremden Seiten wird nur die Domain gesendet, nie ein Pfad.
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Cache-Control: no-store'); // Seiten mit Anmeldung nie zwischenspeichern; Bilder und Farbschema setzen eigene Werte
    header('X-Robots-Tag: noindex, nofollow');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https://tile.openstreetmap.org https://*.tile.openstreetmap.org; "
        . "style-src 'self'; script-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self'");
    if (is_https()) {
        header('Strict-Transport-Security: max-age=15552000');
    }
}

// ---- Sitzung ----------------------------------------------------------------
if (PHP_SAPI !== 'cli') {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    // Eigener Sitzungsordner und lange Lebensdauer: Der Server-Standard (oft 24 Minuten) würde
    // Prüfer im Gelände mitten in der Tour abmelden.
    $sessionDir = STORAGE . '/sessions';
    if (!is_dir($sessionDir)) {
        @mkdir($sessionDir, 0700, true);
    }
    if (is_dir($sessionDir) && is_writable($sessionDir)) {
        ini_set('session.save_path', $sessionDir);
    }
    ini_set('session.gc_maxlifetime', '43200');
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '100');
    session_cache_limiter(''); // Cache-Header setzen wir selbst (sonst würden auch Bilder als "nicht cachebar" ausgeliefert)
    session_name('HYDSESS');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => (base_path() === '' ? '/' : base_path() . '/'),
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    // Sitzung nach 12 Stunden Inaktivitaet beenden
    if (isset($_SESSION['uid']) && time() - (int) ($_SESSION['last'] ?? 0) > 43200) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    if (isset($_SESSION['uid'])) {
        $_SESSION['last'] = time();
    }
}
