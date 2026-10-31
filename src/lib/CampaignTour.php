<?php
declare(strict_types=1);

/**
 * Eine Tour innerhalb eines Prüfdurchgangs. Hier hängt der Tour-Link.
 * Die Arrays enthalten: id = Tour-ID, ct_id, campaign_id, token, link_aktiv, name, beschreibung.
 */
final class CampaignTour
{
    private const SELECT = 'SELECT ct.id AS ct_id, ct.campaign_id, ct.token, ct.link_aktiv, ct.created_at AS ct_created, t.id, t.name, t.beschreibung'
        . ' FROM {campaign_tours} ct JOIN {tours} t ON t.id = ct.tour_id';

    public static function find(int $ctId): ?array
    {
        return $ctId > 0 ? Db::one(self::SELECT . ' WHERE ct.id = ?', [$ctId]) : null;
    }

    public static function byToken(string $token): ?array
    {
        return preg_match('/^[a-f0-9]{64}$/', $token) === 1 ? Db::one(self::SELECT . ' WHERE ct.token = ?', [$token]) : null;
    }

    public static function forTour(int $campaignId, int $tourId): ?array
    {
        return $campaignId > 0 && $tourId > 0 ? Db::one(self::SELECT . ' WHERE ct.campaign_id = ? AND t.id = ?', [$campaignId, $tourId]) : null;
    }

    /** @return array<int, array<string, mixed>> natürlich nach Name sortiert */
    public static function forCampaign(int $campaignId): array
    {
        $rows = Db::all(self::SELECT . ' WHERE ct.campaign_id = ?', [$campaignId]);
        usort($rows, static fn ($a, $b) => strnatcasecmp((string) $a['name'], (string) $b['name']));
        return $rows;
    }

    /** @return int[] */
    public static function tourIds(int $campaignId): array
    {
        return array_map(static fn ($r) => (int) $r['tour_id'], Db::all('SELECT tour_id FROM {campaign_tours} WHERE campaign_id = ?', [$campaignId]));
    }

    public static function newToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function add(int $campaignId, int $tourId): int
    {
        return Db::insert(
            'INSERT INTO {campaign_tours} (campaign_id, tour_id, token, link_aktiv, created_at) VALUES (?, ?, ?, 1, ?)',
            [$campaignId, $tourId, self::newToken(), now()]
        );
    }

    /** Anzahl der Revisionen, die in diesem Durchgang für die Tour erfasst wurden (dann bleibt die Tour im Durchgang). */
    public static function revisionCount(int $campaignId, int $tourId): int
    {
        return (int) Db::val('SELECT COUNT(*) FROM {revisions} WHERE campaign_id = ? AND tour_id = ?', [$campaignId, $tourId]);
    }

    public static function link(array $ct): string
    {
        return abs_url('t/' . $ct['token']);
    }

    /** Link ist nutzbar: freigegeben und Prüfdurchgang aktiv. */
    public static function linkWorks(array $ct): bool
    {
        if ((int) $ct['link_aktiv'] !== 1) {
            return false;
        }
        $c = Campaign::find((int) $ct['campaign_id']);
        return $c !== null && (int) $c['aktiv'] === 1;
    }

    /**
     * Fortschritt je Tour in einem Prüfdurchgang (aktuelle Zuordnung der aktiven Hydranten).
     * @return array<int, array{total:int, done:int, defects:int}> Tour-ID => Zahlen
     */
    public static function progress(int $campaignId): array
    {
        $rows = Db::all(
            'SELECT h.tour_id AS tid, COUNT(*) AS total, SUM(CASE WHEN r.id IS NOT NULL THEN 1 ELSE 0 END) AS done,'
            . ' SUM(CASE WHEN r.ergebnis = ? THEN 1 ELSE 0 END) AS defects'
            . ' FROM {hydrants} h JOIN {campaign_tours} ct ON ct.tour_id = h.tour_id AND ct.campaign_id = ?'
            . ' LEFT JOIN (SELECT r1.id, r1.hydrant_id, r1.ergebnis FROM {revisions} r1'
            . '   JOIN (SELECT hydrant_id, MAX(id) AS mid FROM {revisions} WHERE campaign_id = ? GROUP BY hydrant_id) m ON m.mid = r1.id) r ON r.hydrant_id = h.id'
            . ' WHERE h.aktiv = 1 GROUP BY h.tour_id',
            [Revision::DEFECT, $campaignId, $campaignId]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['tid']] = ['total' => (int) $r['total'], 'done' => (int) $r['done'], 'defects' => (int) $r['defects']];
        }
        return $out;
    }
}
