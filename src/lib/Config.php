<?php
declare(strict_types=1);

final class Config
{
    private static array $data = [];

    public static function file(): string
    {
        // Liegt in storage/, damit ein Update (alles außer storage/ überschreiben) die Konfiguration nie berührt.
        return STORAGE . '/config.php';
    }

    public static function installed(): bool
    {
        return is_file(self::file());
    }

    public static function load(): void
    {
        if (is_file(self::file())) {
            $d = require self::file();
            if (is_array($d)) {
                self::$data = $d;
            }
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$data[$key] ?? $default;
    }
}
