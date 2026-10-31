<?php
declare(strict_types=1);

/** Filter der Hydrantenliste (Suche, Typ, Position, Status, Tour). Wird von der Liste und vom OSM-Export gemeinsam benutzt. */
final class HydrantFilter
{
    /** Prüfstatus: Wert => Anzeige. „offen“ und „geprüft“ beziehen sich auf den aktiven Prüfdurchgang; „Mangel“ ist jeder offene Mangel. */
    public const PRUEF = ['offen' => 'offen', 'ok' => 'geprüft (in Ordnung)', 'mangel' => 'Mangel'];

    /** Wählbare Prüfstatus: ohne aktiven Durchgang nur „Mangel“ (Mängel, die direkt am Hydranten eingetragen wurden). @return array<string,string> */
    public static function pruefOptions(): array
    {
        return Campaign::active() === null ? ['mangel' => self::PRUEF['mangel']] : self::PRUEF;
    }

    /** Filter aus einem Parameter-Array (z. B. $_GET oder $_POST) lesen und prüfen. @return array{q:string,typ:string,lage:string,status:string,tour:string,pruef:string} */
    public static function parse(array $src, ?array $tours = null): array
    {
        $get = static function (string $key, string $default = '') use ($src): string {
            $v = $src[$key] ?? $default;
            return is_string($v) ? trim($v) : $default;
        };
        $f = [
            'q'      => $get('q'),
            'typ'    => $get('typ'),
            'lage'   => $get('lage'),
            'status' => $get('status', 'aktiv'),
            'tour'   => $get('tour'),
            'pruef'  => $get('pruef'),
        ];
        $tourIds = array_map(static fn ($t) => (string) $t['id'], $tours ?? Tour::all());
        if ($f['tour'] !== 'none' && !in_array($f['tour'], $tourIds, true)) {
            $f['tour'] = '';
        }
        if (!HydrantType::exists($f['typ'])) {
            $f['typ'] = '';
        }
        if (!Settings::bool('show_position') || !HydrantPosition::exists($f['lage'])) {
            $f['lage'] = '';
        }
        if (!in_array($f['status'], ['aktiv', 'inaktiv', 'alle'], true)) {
            $f['status'] = 'aktiv';
        }
        if (!isset(self::pruefOptions()[$f['pruef']])) {
            $f['pruef'] = '';
        }
        return $f;
    }

    /** @return array{0: string, 1: array<int, mixed>} [" WHERE …" oder "", Parameter] */
    public static function where(array $f): array
    {
        $where = [];
        $params = [];
        if ($f['status'] !== 'alle') {
            $where[] = 'h.aktiv = ?';
            $params[] = $f['status'] === 'aktiv' ? 1 : 0;
        }
        if ($f['typ'] !== '') {
            $where[] = 'h.typ = ?';
            $params[] = $f['typ'];
        }
        if ($f['lage'] !== '') {
            $where[] = 'h.position_art = ?';
            $params[] = $f['lage'];
        }
        if ($f['tour'] === 'none') {
            $where[] = 'h.tour_id IS NULL';
        } elseif ($f['tour'] !== '') {
            $where[] = 'h.tour_id = ?';
            $params[] = (int) $f['tour'];
        }
        if ($f['pruef'] !== '') {
            $campaign = Campaign::active();
            $cid = $campaign === null ? 0 : (int) $campaign['id'];
            // Neueste Revision des Hydranten überhaupt wurde direkt am Hydranten ohne Prüfdurchgang eingetragen: als Mangel (noch offen) bzw. als „in Ordnung“ (Mangel des Durchgangs behoben)
            $manual = 'EXISTS (SELECT 1 FROM {revisions} x WHERE x.hydrant_id = h.id AND x.campaign_id IS NULL AND x.ergebnis = ?'
                . ' AND x.id = (SELECT MAX(y.id) FROM {revisions} y WHERE y.hydrant_id = h.id))';
            $latest = '(SELECT r.ergebnis FROM {revisions} r WHERE r.hydrant_id = h.id AND r.campaign_id = ' . $cid . ' ORDER BY r.id DESC LIMIT 1)';
            if ($f['pruef'] === 'offen') {
                $where[] = 'EXISTS (SELECT 1 FROM {campaign_tours} ct WHERE ct.tour_id = h.tour_id AND ct.campaign_id = ' . $cid . ')'
                    . ' AND NOT EXISTS (SELECT 1 FROM {revisions} r WHERE r.hydrant_id = h.id AND r.campaign_id = ' . $cid . ')';
            } elseif ($f['pruef'] === 'ok') {
                $where[] = '(' . $latest . ' = ? OR (' . $latest . ' = ? AND ' . $manual . '))';
                array_push($params, Revision::OK, Revision::DEFECT, Revision::OK);
            } else {
                $where[] = '((' . $latest . ' = ? AND NOT ' . $manual . ') OR ' . $manual . ')';
                array_push($params, Revision::DEFECT, Revision::OK, Revision::DEFECT);
            }
        }
        if ($f['q'] !== '') {
            // "!" als Escape-Zeichen, damit % und _ in der Suche als normale Zeichen gelten (gleich in MySQL und SQLite).
            $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $f['q']) . '%';
            $where[] = "(h.kennung LIKE ? ESCAPE '!' OR h.bemerkung LIKE ? ESCAPE '!')";
            array_push($params, $like, $like);
        }
        return [$where ? ' WHERE ' . implode(' AND ', $where) : '', $params];
    }

    /** Gesetzte Filter als Adressparameter (Status immer, damit „aktive“ eindeutig bleibt). @return array<string, string> */
    public static function toQuery(array $f): array
    {
        $q = ['status' => $f['status']];
        foreach (['q', 'typ', 'lage', 'tour', 'pruef'] as $k) {
            if ($f[$k] !== '') {
                $q[$k] = $f[$k];
            }
        }
        return $q;
    }

    /** Lesbare Beschreibung, z. B. „aktive Hydranten · Typ Unterflur · Tour Nord“. */
    public static function describe(array $f): string
    {
        $parts = [['aktiv' => 'aktive Hydranten', 'inaktiv' => 'deaktivierte Hydranten', 'alle' => 'aktive und deaktivierte Hydranten'][$f['status']]];
        if ($f['typ'] !== '') {
            $parts[] = 'Typ „' . HydrantType::label($f['typ']) . '“';
        }
        if ($f['lage'] !== '') {
            $parts[] = 'Position „' . HydrantPosition::label($f['lage']) . '“';
        }
        if ($f['tour'] === 'none') {
            $parts[] = 'ohne Tour';
        } elseif ($f['tour'] !== '') {
            $name = '';
            foreach (Tour::all() as $t) {
                if ((string) $t['id'] === $f['tour']) {
                    $name = (string) $t['name'];
                }
            }
            $parts[] = 'Tour „' . $name . '“';
        }
        if ($f['pruef'] !== '') {
            $parts[] = 'Prüfstatus „' . self::PRUEF[$f['pruef']] . '“';
        }
        if ($f['q'] !== '') {
            $parts[] = 'Suche „' . $f['q'] . '“';
        }
        return implode(' · ', $parts);
    }
}
