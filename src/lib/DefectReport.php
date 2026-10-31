<?php
declare(strict_types=1);

/** Mängelübersicht: je Hydrant und Prüfdurchgang zählt die letzte Revision; Revisionen ohne Prüfdurchgang zählen, bis der Hydrant später als in Ordnung geprüft wird. */
final class DefectReport
{
    /** Auswahlwert für Revisionen, die in der App ohne Prüfdurchgang erfasst wurden. */
    public const NO_CAMPAIGN = 0;

    private const SELECT = 'SELECT r.id AS rev_id, r.campaign_id, c.name AS campaign_name, c.start_date, t.name AS tour_name, h.id AS hydrant_id, h.kennung, h.typ, h.position_art,'
        . ' h.lat, h.lon, r.ergebnis, r.bemerkung, r.revised_at, r.quelle, o.teilnehmer, u.name AS user_name,'
        . ' (SELECT COUNT(*) FROM {revision_images} ri WHERE ri.revision_id = r.id) AS fotos'
        . ' FROM {revisions} r';
    private const JOINS = ' JOIN {hydrants} h ON h.id = r.hydrant_id'
        . ' LEFT JOIN {campaigns} c ON c.id = r.campaign_id'
        . ' LEFT JOIN {tours} t ON t.id = r.tour_id'
        . ' LEFT JOIN {tour_outings} o ON o.id = r.outing_id'
        . ' LEFT JOIN {users} u ON u.id = r.user_id';

    /**
     * @param int[] $campaignIds Prüfdurchgänge; 0 (NO_CAMPAIGN) steht für „ohne Prüfdurchgang“
     * @return array<int, array<string, mixed>>
     */
    public static function rows(array $campaignIds, string $tourName, bool $withOk): array
    {
        $campaignIds = array_values(array_unique(array_filter(array_map('intval', $campaignIds), static fn ($i) => $i >= 0)));
        $withoutCampaign = in_array(self::NO_CAMPAIGN, $campaignIds, true);
        $campaignIds = array_values(array_filter($campaignIds, static fn ($i) => $i > 0));
        $filter = '';
        $filterParams = [];
        if (!$withOk) {
            $filter .= ' AND r.ergebnis = ?';
            $filterParams[] = Revision::DEFECT;
        }
        if ($tourName !== '') {
            $filter .= ' AND t.name = ?';
            $filterParams[] = $tourName;
        }
        $rows = [];
        if ($campaignIds) {
            $in = implode(',', array_fill(0, count($campaignIds), '?'));
            // Je Hydrant und Prüfdurchgang zählt die letzte Revision.
            $rows = Db::all(
                self::SELECT
                . ' JOIN (SELECT campaign_id, hydrant_id, MAX(id) AS mid FROM {revisions} WHERE campaign_id IN (' . $in . ') GROUP BY campaign_id, hydrant_id) m ON m.mid = r.id'
                . self::JOINS . ' WHERE 1 = 1' . $filter,
                array_merge($campaignIds, $filterParams)
            );
        }
        if ($withoutCampaign) {
            // Ohne Prüfdurchgang zählt die neueste Revision des Hydranten überhaupt: Eine spätere Prüfung „in Ordnung“ (in der App oder in einem Durchgang) erledigt den Mangel.
            $rows = array_merge($rows, Db::all(
                self::SELECT
                . ' JOIN (SELECT hydrant_id, MAX(id) AS mid FROM {revisions} GROUP BY hydrant_id) m ON m.mid = r.id'
                . self::JOINS . ' WHERE r.campaign_id IS NULL' . $filter,
                $filterParams
            ));
        }
        foreach ($rows as &$r) {
            $r['defects'] = array_map(
                static fn ($d) => (string) $d['name'],
                Db::all('SELECT d.name FROM {revision_defects} rd JOIN {defect_types} d ON d.id = rd.defect_type_id WHERE rd.revision_id = ? ORDER BY d.sortierung, d.id', [(int) $r['rev_id']])
            );
        }
        unset($r);
        // Zuerst „ohne Prüfdurchgang“ (die aktuell offenen Mängel), dann die Durchgänge, neueste zuerst.
        usort($rows, static function (array $a, array $b): int {
            $ka = $a['campaign_id'] === null ? ['9999-12-31', PHP_INT_MAX] : [$a['start_date'], (int) $a['campaign_id']];
            $kb = $b['campaign_id'] === null ? ['9999-12-31', PHP_INT_MAX] : [$b['start_date'], (int) $b['campaign_id']];
            return $kb <=> $ka
                ?: strnatcasecmp((string) $a['tour_name'], (string) $b['tour_name'])
                ?: strnatcasecmp((string) $a['kennung'], (string) $b['kennung']);
        });
        return $rows;
    }

    public static function campaignLabel(array $row): string
    {
        return $row['campaign_name'] === null ? 'Ohne Prüfdurchgang' : (string) $row['campaign_name'];
    }

    /** Namen aller Touren für den Tourfilter (Touren sind dauerhaft, daher immer die vollständige Liste). @return string[] */
    public static function tourNames(): array
    {
        return array_map(static fn ($t) => (string) $t['name'], Tour::all());
    }

    public static function csv(array $rows): string
    {
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                self::campaignLabel($r),
                $r['tour_name'] ?? '',
                $r['kennung'],
                Hydrant::typLabel($r['typ']),
                Hydrant::lageLabel($r['position_art']),
                $r['lat'] === null ? '' : Hydrant::coord($r['lat']),
                $r['lon'] === null ? '' : Hydrant::coord($r['lon']),
                format_dt($r['revised_at']),
                $r['ergebnis'] === Revision::DEFECT ? 'Mangel' : 'in Ordnung',
                implode(' | ', $r['defects']),
                $r['bemerkung'] ?? '',
                $r['quelle'] === 'tour' ? ($r['teilnehmer'] ?? '') : ($r['user_name'] ?? ''),
                $r['quelle'] === 'tour' ? 'Tour-Link' : 'Anwendung',
                (string) $r['fotos'],
            ];
        }
        return Csv::write(
            ['Prüfdurchgang', 'Tour', 'Kennung', 'Typ', 'Position', 'Breitengrad', 'Längengrad', 'Geprüft am', 'Ergebnis', 'Mängel', 'Bemerkung', 'Prüfer', 'Quelle', 'Fotos'],
            $out
        );
    }
}
