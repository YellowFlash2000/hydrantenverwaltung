<?php
declare(strict_types=1);

final class Settings
{
    public const DEFAULTS = [
        'app_name'       => 'Hydrantenverwaltung',
        'color_primary'  => '#c8102e',
        'color_header'   => '#1d1d1b',
        'color_bg'       => '#f3f3f1',
        'logo_file'      => '',
        'theme_version'  => '1',
        'schema_version' => '1',
        'show_pressure'  => '1',
        'show_flow'      => '1',
        'show_position'  => '1',
        'map_lat'        => '',
        'map_lon'        => '',
        'map_zoom'       => '16',
        'mail_host'      => '',
        'mail_port'      => '587',
        'mail_secure'    => 'tls',
        'mail_user'      => '',
        'mail_pass_enc'  => '',
        'mail_from'      => '',
        'mail_from_name' => '',
        'audit_retention_months' => '0',
        'audit_cleanup_last' => '',
    ];

    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            $values = self::DEFAULTS;
            foreach (Db::all('SELECT skey, svalue FROM {settings}') as $row) {
                $values[$row['skey']] = (string) $row['svalue'];
            }
            self::$cache = $values;
        }
        return self::$cache;
    }

    public static function get(string $key): string
    {
        return self::all()[$key] ?? '';
    }

    public static function bool(string $key): bool
    {
        return self::get($key) === '1';
    }

    public static function set(string $key, string $value): void
    {
        $exists = Db::val('SELECT COUNT(*) FROM {settings} WHERE skey = ?', [$key]);
        if ((int) $exists > 0) {
            Db::exec('UPDATE {settings} SET svalue = ? WHERE skey = ?', [$value, $key]);
        } else {
            Db::exec('INSERT INTO {settings} (skey, svalue) VALUES (?, ?)', [$key, $value]);
        }
        self::$cache = null;
    }

    public static function setMany(array $values): void
    {
        foreach ($values as $k => $v) {
            self::set((string) $k, (string) $v);
        }
    }

    /** Standard-Kartenausschnitt aus den Einstellungen: [Breite, Länge, Zoom] oder null, wenn nicht festgelegt. */
    public static function mapDefault(): ?array
    {
        $lat = Hydrant::parseDecimal(self::get('map_lat'));
        $lon = Hydrant::parseDecimal(self::get('map_lon'));
        if ($lat === null || $lon === null) {
            return null;
        }
        $zoom = (int) self::get('map_zoom');
        return [$lat, $lon, ($zoom >= 5 && $zoom <= 19) ? $zoom : 16];
    }

    public static function bumpTheme(): void
    {
        self::set('theme_version', (string) time());
    }
}
