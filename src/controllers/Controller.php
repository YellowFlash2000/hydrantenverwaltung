<?php
declare(strict_types=1);

abstract class Controller
{
    protected function render(string $view, array $data = [], int $status = 200): void
    {
        http_response_code($status);
        View::render($view, $data);
    }

    protected function post(string $key, string $default = ''): string
    {
        $v = $_POST[$key] ?? $default;
        return is_string($v) ? trim($v) : $default;
    }

    protected function fail(int $code, string $title, string $message): void
    {
        Router::error($code, $title, $message);
    }
}
