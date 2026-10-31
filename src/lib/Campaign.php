<?php
declare(strict_types=1);

/** Prüfdurchgang (z. B. „Prüfung 2026“). Es gibt immer höchstens einen aktiven. */
final class Campaign
{
    public static function find(int $id): ?array
    {
        return $id > 0 ? Db::one('SELECT * FROM {campaigns} WHERE id = ?', [$id]) : null;
    }

    public static function active(): ?array
    {
        return Db::one('SELECT * FROM {campaigns} WHERE aktiv = 1 ORDER BY id DESC LIMIT 1');
    }

    /** @return array<int, array<string, mixed>> neueste zuerst */
    public static function all(): array
    {
        return Db::all('SELECT * FROM {campaigns} ORDER BY start_date DESC, id DESC');
    }

    /** Der zuletzt angelegte Prüfdurchgang (Vorlage für die Vorauswahl der Touren). */
    public static function previous(): ?array
    {
        return Db::one('SELECT * FROM {campaigns} ORDER BY id DESC LIMIT 1');
    }

    /**
     * Legt einen Prüfdurchgang mit den gewählten Touren an (je Tour ein neuer Link) und beendet den bisher aktiven.
     * @param int[] $tourIds
     */
    public static function create(string $name, string $start, array $tourIds): int
    {
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            Db::exec('UPDATE {campaigns} SET aktiv = 0, end_date = COALESCE(end_date, ?) WHERE aktiv = 1', [date('Y-m-d')]);
            $id = Db::insert('INSERT INTO {campaigns} (name, start_date, end_date, aktiv, created_at) VALUES (?, ?, NULL, 1, ?)', [$name, $start, now()]);
            foreach (array_unique($tourIds) as $tid) {
                if (Tour::find((int) $tid) !== null) {
                    CampaignTour::add($id, (int) $tid);
                }
            }
            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function end(int $id): void
    {
        Db::exec('UPDATE {campaigns} SET aktiv = 0, end_date = COALESCE(end_date, ?) WHERE id = ?', [date('Y-m-d'), $id]);
    }

    /** Wieder öffnen, nur wenn kein anderer Durchgang aktiv ist. */
    public static function reopen(int $id): bool
    {
        if (self::active() !== null) {
            return false;
        }
        Db::exec('UPDATE {campaigns} SET aktiv = 1, end_date = NULL WHERE id = ?', [$id]);
        return true;
    }

    /** Statistik eines Prüfdurchgangs: geprüft (zuletzt gültige Wertung je Hydrant) und Mängel. @return array{checked:int, defects:int} */
    public static function stats(int $id): array
    {
        $row = Db::one(
            'SELECT COUNT(*) AS n, SUM(CASE WHEN r.ergebnis = ? THEN 1 ELSE 0 END) AS m FROM {revisions} r'
            . ' JOIN (SELECT hydrant_id, MAX(id) AS mid FROM {revisions} WHERE campaign_id = ? GROUP BY hydrant_id) x ON x.mid = r.id',
            [Revision::DEFECT, $id]
        );
        return ['checked' => (int) ($row['n'] ?? 0), 'defects' => (int) ($row['m'] ?? 0)];
    }
}
