<?php
declare(strict_types=1);

/** Erzeugt das Farbschema (CSS-Variablen) aus den Einstellungen. */
final class Theme
{
    public static function validColor(string $c): bool
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $c) === 1;
    }

    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    private static function luminance(string $hex): float
    {
        [$r, $g, $b] = array_map(static function (int $v): float {
            $c = $v / 255;
            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));
        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    /** Lesbare Textfarbe (hell oder dunkel) fuer einen Hintergrund. */
    public static function textOn(string $hex): string
    {
        return self::luminance($hex) > 0.4 ? '#111111' : '#ffffff';
    }

    public static function darken(string $hex, float $factor): string
    {
        [$r, $g, $b] = self::rgb($hex);
        return sprintf('#%02x%02x%02x', (int) round($r * (1 - $factor)), (int) round($g * (1 - $factor)), (int) round($b * (1 - $factor)));
    }

    public static function css(): string
    {
        $s = Settings::all();
        $primary = self::validColor($s['color_primary']) ? $s['color_primary'] : Settings::DEFAULTS['color_primary'];
        $header = self::validColor($s['color_header']) ? $s['color_header'] : Settings::DEFAULTS['color_header'];
        $bg = self::validColor($s['color_bg']) ? $s['color_bg'] : Settings::DEFAULTS['color_bg'];

        return ":root {\n"
            . "  --c-primary: {$primary};\n"
            . '  --c-primary-dark: ' . self::darken($primary, 0.15) . ";\n"
            . '  --c-primary-text: ' . self::textOn($primary) . ";\n"
            . "  --c-header: {$header};\n"
            . '  --c-header-text: ' . self::textOn($header) . ";\n"
            . "  --c-bg: {$bg};\n"
            . "}\n";
    }
}
