<?php
declare(strict_types=1);

/** Mängelkatalog: Auswahl der Mängel bei einer Revision, je Hydrantentyp unterschiedlich. */
final class DefectType
{
    /** @return array<int, array<string, mixed>> */
    public static function all(): array
    {
        return Db::all('SELECT id, name, sortierung, aktiv, gilt_fuer FROM {defect_types} ORDER BY sortierung, id');
    }

    /** @return string[] Typen, für die ein Mangel gilt (leer = alle). */
    public static function types(array $d): array
    {
        $v = trim((string) ($d['gilt_fuer'] ?? ''));
        return $v === '' ? array_keys(HydrantType::all()) : array_values(array_filter(explode(',', $v)));
    }

    /** Aktive Mängel, die bei diesem Hydrantentyp zur Auswahl stehen. @return array<int, array<string, mixed>> */
    public static function forType(string $typ): array
    {
        return array_values(array_filter(
            Db::all('SELECT id, name, sortierung, aktiv, gilt_fuer FROM {defect_types} WHERE aktiv = 1 ORDER BY sortierung, id'),
            static fn (array $d): bool => in_array($typ, self::types($d), true)
        ));
    }

    /** @param int[] $ids @return int[] nur die IDs, die für den Typ erlaubt sind */
    public static function filterAllowed(string $typ, array $ids): array
    {
        $allowed = array_map(static fn ($d) => (int) $d['id'], self::forType($typ));
        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn ($i) => in_array($i, $allowed, true))));
    }
}
