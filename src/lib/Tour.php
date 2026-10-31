<?php
declare(strict_types=1);

/** Revisionstour: ein fester Plan (Gruppe von Hydranten), der dauerhaft bestehen bleibt. */
final class Tour
{
    public static function find(int $id): ?array
    {
        return $id > 0 ? Db::one('SELECT * FROM {tours} WHERE id = ?', [$id]) : null;
    }

    /** @return array<int, array<string, mixed>> natürlich nach Name sortiert */
    public static function all(): array
    {
        $rows = Db::all('SELECT * FROM {tours}');
        usort($rows, static fn ($a, $b) => strnatcasecmp((string) $a['name'], (string) $b['name']));
        return $rows;
    }

    public static function findByName(string $name): ?array
    {
        return Db::one('SELECT * FROM {tours} WHERE name = ?', [$name]);
    }

    public static function nameTaken(string $name, ?int $exceptId = null): bool
    {
        return (int) Db::val('SELECT COUNT(*) FROM {tours} WHERE LOWER(name) = ? AND id <> ?', [mb_strtolower($name), (int) $exceptId]) > 0;
    }

    public static function insert(string $name, ?string $description): int
    {
        // Bei Datenbanken, die von einer älteren Version aktualisiert wurden, gibt es noch die alte Spalte „token“ (Pflichtfeld ohne Bedeutung).
        if (Db::columnExists('tours', 'token')) {
            return Db::insert(
                'INSERT INTO {tours} (name, beschreibung, token, link_aktiv, created_at) VALUES (?, ?, ?, 0, ?)',
                [$name, $description, bin2hex(random_bytes(32)), now()]
            );
        }
        return Db::insert('INSERT INTO {tours} (name, beschreibung, created_at) VALUES (?, ?, ?)', [$name, $description, now()]);
    }

    /** Anzahl aktiver Hydranten einer Tour. */
    public static function hydrantCount(int $tourId): int
    {
        return (int) Db::val('SELECT COUNT(*) FROM {hydrants} WHERE tour_id = ? AND aktiv = 1', [$tourId]);
    }

    /** @return array<int, int> Tour-ID => Anzahl aktiver Hydranten */
    public static function hydrantCounts(): array
    {
        $out = [];
        foreach (Db::all('SELECT tour_id, COUNT(*) AS n FROM {hydrants} WHERE tour_id IS NOT NULL AND aktiv = 1 GROUP BY tour_id') as $r) {
            $out[(int) $r['tour_id']] = (int) $r['n'];
        }
        return $out;
    }

    /**
     * Aktive Hydranten einer Tour samt Wertung in einem Prüfdurchgang ($campaignId = 0: ohne Wertung).
     * @return array<int, array<string, mixed>> natürlich nach Kennung sortiert; Felder rev_id, rev_ergebnis, rev_at
     */
    public static function hydrants(int $tourId, int $campaignId): array
    {
        $rows = Db::all(
            'SELECT h.id, h.kennung, h.typ, h.durchmesser_dn, h.durchfluss_lmin, h.durchfluss_zeichen, h.position_art, h.lat, h.lon, h.aktiv, h.bemerkung,'
            . ' r.id AS rev_id, r.ergebnis AS rev_ergebnis, r.revised_at AS rev_at'
            . ' FROM {hydrants} h'
            . ' LEFT JOIN (SELECT r1.id, r1.hydrant_id, r1.ergebnis, r1.revised_at FROM {revisions} r1'
            . '   JOIN (SELECT hydrant_id, MAX(id) AS mid FROM {revisions} WHERE campaign_id = ? GROUP BY hydrant_id) m ON m.mid = r1.id) r ON r.hydrant_id = h.id'
            . ' WHERE h.tour_id = ? AND h.aktiv = 1',
            [$campaignId, $tourId]
        );
        usort($rows, static fn ($a, $b) => strnatcasecmp((string) $a['kennung'], (string) $b['kennung']));
        return $rows;
    }

    /** Anzahl der Revisionen, die zu dieser Tour erfasst wurden. */
    public static function revisionCount(int $tourId): int
    {
        return (int) Db::val('SELECT COUNT(*) FROM {revisions} WHERE tour_id = ?', [$tourId]);
    }

    /** In wie vielen Prüfdurchgängen die Tour vorkommt. */
    public static function campaignCount(int $tourId): int
    {
        return (int) Db::val('SELECT COUNT(*) FROM {campaign_tours} WHERE tour_id = ?', [$tourId]);
    }
}
