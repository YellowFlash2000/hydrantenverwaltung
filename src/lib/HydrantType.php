<?php
declare(strict_types=1);

/** Hydrantentypen: vom Admin pflegbar, jeder Typ hat ein Symbol für Karte und Liste. */
final class HydrantType
{
    /** Voreinstellung bei der Installation: Schlüssel => [Name, Symbol] */
    public const DEFAULTS = [
        'unterflur'   => ['Unterflur', 'dreieck_runter'],
        'ueberflur'   => ['Überflur', 'dreieck_hoch'],
        'saugbrunnen' => ['Saugbrunnen', 'quadrat'],
    ];

    /** Voreingestellter OSM-Wert für fire_hydrant:type (leer = nicht exportieren) */
    public const OSM_DEFAULTS = [
        'unterflur'   => 'underground',
        'ueberflur'   => 'pillar',
        'saugbrunnen' => 'pipe',
    ];

    /** Zulässige Werte für fire_hydrant:type in OpenStreetMap: Wert => Anzeige */
    public const OSM_VALUES = [
        'pillar'      => 'pillar (Überflur)',
        'underground' => 'underground (Unterflur)',
        'wall'        => 'wall (Wand)',
        'pipe'        => 'pipe (Saugbrunnen)',
    ];

    /** Auswahl der Symbole: Schlüssel => Bezeichnung */
    public const SYMBOLS = [
        'dreieck_runter' => 'Dreieck nach unten',
        'dreieck_hoch'   => 'Dreieck nach oben',
        'quadrat'        => 'Quadrat',
        'kreis'          => 'Kreis',
        'raute'          => 'Raute',
        'kreuz'          => 'Kreuz',
        'stern'          => 'Stern',
    ];

    /** Formen im 20×20-Raster. Muss mit assets/js/map.js übereinstimmen. */
    private const SHAPES = [
        'dreieck_runter' => ['polygon', '2,3 18,3 10,18'],
        'dreieck_hoch'   => ['polygon', '10,2 18,17 2,17'],
        'quadrat'        => ['rect', ''],
        'kreis'          => ['circle', ''],
        'raute'          => ['polygon', '10,1.5 18.5,10 10,18.5 1.5,10'],
        'kreuz'          => ['polygon', '7,2 13,2 13,7 18,7 18,13 13,13 13,18 7,18 7,13 2,13 2,7 7,7'],
        'stern'          => ['polygon', '10,1.5 12.35,7.4 18.7,7.8 13.8,11.9 15.4,18 10,14.6 4.6,18 6.2,11.9 1.3,7.8 7.65,7.4'],
    ];

    /** @var ?array<string, array<string, mixed>> */
    private static ?array $cache = null;

    /** @return array<string, array<string, mixed>> Schlüssel => Zeile, in Reihenfolge; inkl. gesperrter Typen */
    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (Db::all('SELECT id, schluessel, name, symbol, osm_wert, sortierung, aktiv FROM {hydrant_types} ORDER BY sortierung, id') as $r) {
                self::$cache[(string) $r['schluessel']] = $r;
            }
        }
        return self::$cache;
    }

    public static function reset(): void
    {
        self::$cache = null;
    }

    /** @return array<string, string> Schlüssel => Name der wählbaren (aktiven) Typen; $keep bleibt zusätzlich wählbar */
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

    /** @return array<string, string> Schlüssel => Name aller Typen */
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

    public static function symbolOf(?string $key): string
    {
        return (string) (self::all()[(string) $key]['symbol'] ?? 'kreis');
    }

    public static function usedCount(string $key): int
    {
        return (int) Db::val('SELECT COUNT(*) FROM {hydrants} WHERE typ = ?', [$key]);
    }

    /** Neuer, eindeutiger Schlüssel aus dem Namen. */
    public static function newKey(string $name, array $taken): string
    {
        $s = strtr(mb_strtolower($name), ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
        $s = trim((string) preg_replace('/[^a-z0-9]+/', '_', $s), '_');
        $s = substr($s === '' ? 'typ' : $s, 0, 24);
        $key = $s;
        for ($i = 2; in_array($key, $taken, true); $i++) {
            $key = $s . '_' . $i;
        }
        return $key;
    }

    /** Inline-SVG für Tabellen und Legenden. Farbe über CSS (.sym), der Name steht als Text daneben oder im title. */
    public static function svg(string $symbol, int $size = 16, string $class = 'sym'): string
    {
        [$kind, $pts] = self::SHAPES[$symbol] ?? self::SHAPES['quadrat'];
        $shape = match ($kind) {
            'rect'   => '<rect x="3" y="3" width="14" height="14"/>',
            'circle' => '<circle cx="10" cy="10" r="7"/>',
            default  => '<polygon points="' . $pts . '"/>',
        };
        if (!isset(self::SHAPES[$symbol])) {
            $shape = '<circle cx="10" cy="10" r="7"/>';
        }
        return '<svg class="' . e($class) . '" viewBox="0 0 20 20" width="' . $size . '" height="' . $size . '" aria-hidden="true" focusable="false">' . $shape . '</svg>';
    }

    /** Symbol eines Typs samt unsichtbarem Namen für Screenreader und Tooltip. */
    public static function icon(?string $key, int $size = 16): string
    {
        $name = self::label($key);
        return '<span class="type-icon" title="' . e($name) . '">' . self::svg(self::symbolOf($key), $size) . '<span class="visually-hidden">' . e($name) . '</span></span>';
    }
}
