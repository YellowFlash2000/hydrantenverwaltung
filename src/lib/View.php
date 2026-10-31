<?php
declare(strict_types=1);

final class View
{
    public static function render(string $view, array $data = []): void
    {
        $file = SRC . '/views/' . $view . '.php';
        $render = static function (string $__file, array $__data): string {
            extract($__data, EXTR_SKIP);
            ob_start();
            require $__file;
            return (string) ob_get_clean();
        };
        $content = $render($file, $data);
        $title = (string) ($data['title'] ?? '');
        $wide = (bool) ($data['wide'] ?? false);
        $styles = (array) ($data['styles'] ?? []);   // zusätzliche CSS-Dateien unter assets/
        $scripts = (array) ($data['scripts'] ?? []); // zusätzliche JavaScript-Dateien unter assets/ (in dieser Reihenfolge)
        require SRC . '/views/layout.php';
    }
}
