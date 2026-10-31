<?php
declare(strict_types=1);

final class Router
{
    /** Anwendungsinterner Pfad der aktuellen Anfrage, z. B. "/admin/benutzer". */
    public static function path(): string
    {
        $uri = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
        $uri = rawurldecode($uri);
        $base = base_path();
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        if (str_starts_with($uri, '/index.php')) {
            $uri = substr($uri, strlen('/index.php'));
        }
        return '/' . trim($uri, '/');
    }

    /**
     * @param array<int, array{0:string,1:string,2:array{0:class-string,1:string},3:?string}> $routes
     */
    public static function dispatch(array $routes): void
    {
        $path = self::path();
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'HEAD') {
            $method = 'GET';
        }
        $pathMatched = false;

        foreach ($routes as [$m, $pattern, $handler, $role]) {
            $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
            if (preg_match($regex, $path, $mm) !== 1) {
                continue;
            }
            $pathMatched = true;
            if ($m !== $method) {
                continue;
            }
            $params = array_filter($mm, 'is_string', ARRAY_FILTER_USE_KEY);

            if ($role !== null) {
                if (!Auth::check()) {
                    if ($method === 'GET') {
                        $_SESSION['next'] = $path;
                    }
                    redirect('login');
                }
                if ($role !== 'login' && !Auth::hasRole($role)) {
                    self::error(403, 'Kein Zugriff', 'Für diese Seite fehlt Ihnen die Berechtigung.');
                    return;
                }
            }
            if ($method === 'POST' && empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
                // PHP verwirft Formulardaten komplett, wenn post_max_size überschritten ist (typisch bei Foto-Uploads).
                self::error(413, 'Upload zu groß', 'Die gesendeten Daten sind für den Server zu groß (erlaubt: ' . ini_get('post_max_size') . '). Bitte weniger oder kleinere Bilder auswählen.');
                return;
            }
            if ($method === 'POST' && !csrf_valid()) {
                self::error(419, 'Sitzung abgelaufen', 'Das Formular ist nicht mehr gültig. Bitte laden Sie die Seite neu und versuchen Sie es erneut.');
                return;
            }

            [$class, $action] = $handler;
            (new $class())->$action($params);
            return;
        }

        if ($pathMatched) {
            self::error(405, 'Nicht erlaubt', 'Diese Aktion ist hier nicht möglich.');
        } else {
            self::error(404, 'Seite nicht gefunden', 'Die angeforderte Seite gibt es nicht.');
        }
    }

    public static function error(int $code, string $title, string $message): void
    {
        http_response_code($code);
        View::render('error', ['title' => $title, 'code' => $code, 'message' => $message]);
    }
}
