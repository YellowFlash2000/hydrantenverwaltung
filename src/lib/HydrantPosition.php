<?php
declare(strict_types=1);

/** Positionen eines Hydranten (z. B. Gehweg): vom Admin pflegbar. */
final class HydrantPosition
{
    public const DEFAULTS = [
        'gruenstreifen' => 'Grünstreifen',
        'gehweg'        => 'Gehweg',
        'parkplatz'     => 'Parkplatz',
        'strasse'       => 'Straße',
    ];

    /** Voreingestellter OSM-Wert für fire_hydrant:position (leer = nicht exportieren) */
    public const OSM_DEFAULTS = [
        'gruenstreifen' => 'green',
        'gehweg'        => 'sidewalk',
        'parkplatz'     => 'parking_lot',
        'strasse'       => 'lane',
    ];

    /** Zulässige Werte für fire_hydrant:position in OpenStreetMap: Wert => Anzeige */
    public const OSM_VALUES = [
        'green'       => 'green (Grünstreifen)',
        'sidewalk'    => 'sidewalk (Gehweg)',
        'parking_lot' => 'parking_lot (Parkplatz)',
        'lane'        => 'lane (Fahrbahn)',
    ];

    /** @var ?array<string, array<string, mixed>> */
    private static ?array $cache = null;

    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (Db::all('SELECT id, schluessel, name, osm_wert, sortierung, aktiv FROM {positions} ORDER BY sortierung, id') as $r) {
                self::$cache[(string) $r['schluessel']] = $r;
            }
        }
        return self::$cache;
    }

    public static function reset(): void
    {
        self::$cache = null;
    }

    /** @return array<string, string> wählbare (aktive) Positionen; $keep bleibt zusätzlich wählbar */
    public static function choices(?string $keep = null): array
    {
        $out = [];
        foreach (self::all() as $k => $r) {
            if ((int) $r['aktiv'] === 1 || $k === $keep) {
                $out[$k] = (string) $r['name'];
            }
        }
        return $out;
    }

    /** @return array<string, string> alle Positionen */
    public static function labels(): array
    {
        return array_map(static fn ($r) => (string) $r['name'], self::all());
    }

    public static function exists(string $key): bool
    {
        return isset(self::all()[$key]);
    }

    public static function label(?string $key): string
    {
        return (string) (self::all()[(string) $key]['name'] ?? '–');
    }

    public static function usedCount(string $key): int
    {
        return (int) Db::val('SELECT COUNT(*) FROM {hydrants} WHERE position_art = ?', [$key]);
    }
}
