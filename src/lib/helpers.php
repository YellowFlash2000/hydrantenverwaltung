<?php
declare(strict_types=1);

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_https(): bool
{
    $https = $_SERVER['HTTPS'] ?? '';
    if ($https !== '' && strtolower((string) $https) !== 'off') {
        return true;
    }
    if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        return true;
    }
    return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

/** Pfad des Ordners, in dem die Anwendung liegt (ohne abschliessenden Schraegstrich). */
function base_path(): string
{
    static $base = null;
    if ($base === null) {
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        $dir = rtrim(dirname($script), '/');
        $base = ($dir === '' || $dir === '.' || $dir === '/') ? '' : $dir;
    }
    return $base;
}

/** Anwendungsinterner Pfad, je nach Einstellung mit oder ohne index.php. */
function url_rel(string $path = '', array $query = []): string
{
    $path = ltrim($path, '/');
    $pretty = (bool) Config::get('pretty_urls', true);
    $u = $pretty ? '/' . $path : '/index.php' . ($path !== '' ? '/' . $path : '');
    if ($query) {
        $u .= '?' . http_build_query($query);
    }
    return $u;
}

function url(string $path = '', array $query = []): string
{
    return base_path() . url_rel($path, $query);
}

/** Absolute URL (fuer E-Mails), basiert auf der bei der Installation festgelegten Basis-URL. */
function abs_url(string $path = '', array $query = []): string
{
    $base = rtrim((string) Config::get('base_url', ''), '/');
    return $base . url_rel($path, $query);
}

function asset(string $path): string
{
    $file = APP_ROOT . '/assets/' . $path;
    $v = is_file($file) ? (string) filemtime($file) : '0';
    return base_path() . '/assets/' . $path . '?v=' . $v;
}

function redirect(string $path, array $query = []): never
{
    header('Location: ' . url($path, $query));
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = [$type, $message];
}

function flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $sent = $_POST['_csrf'] ?? '';
    return is_string($sent) && $sent !== '' && hash_equals(csrf_token(), $sent);
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function format_dt(?string $dt, bool $withTime = true): string
{
    if ($dt === null || $dt === '') {
        return '–';
    }
    $ts = strtotime($dt);
    return $ts === false ? '–' : date($withTime ? 'd.m.Y H:i' : 'd.m.Y', $ts);
}

/** Nur interne, einfache Pfade als Weiterleitungsziel zulassen. */
function safe_next(?string $path): ?string
{
    if ($path === null || $path === '' || $path[0] !== '/' || str_starts_with($path, '//')) {
        return null;
    }
    return preg_match('#^/[A-Za-z0-9/_\-.]*$#', $path) === 1 ? $path : null;
}
