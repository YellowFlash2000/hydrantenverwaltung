<?php
declare(strict_types=1);

/** Anzeige des Protokolls (nur Admin): Filter, Seiten, Klartext, Verweise auf Objekte, Aufbewahrung. */
final class AuditLog
{
    public const PER_PAGE = 50;
    public const MAX_EXPORT = 50000;

    /** Aufbewahrung in Monaten (0 = unbegrenzt): Wert => Anzeige */
    public const RETENTION = [0 => 'unbegrenzt', 12 => '12 Monate', 24 => '24 Monate', 36 => '36 Monate', 60 => '60 Monate'];

    /** Aktionen in Klartext: Schlüssel => Bezeichnung */
    public const LABELS = [
        'login' => 'Angemeldet', 'logout' => 'Abgemeldet', 'login_fehlgeschlagen' => 'Anmeldung fehlgeschlagen',
        'passwort_reset_angefordert' => 'Passwort-Link angefordert', 'passwort_gesetzt' => 'Passwort gesetzt', 'passwort_geaendert' => 'Passwort geändert',
        'hydrant_angelegt' => 'Hydrant angelegt', 'hydrant_geaendert' => 'Hydrant geändert', 'hydrant_aktiviert' => 'Hydrant aktiviert', 'hydrant_deaktiviert' => 'Hydrant deaktiviert',
        'hydrant_tour_geaendert' => 'Tour des Hydranten geändert', 'bilder_hochgeladen' => 'Bilder hochgeladen', 'bild_beschriftet' => 'Bild beschriftet',
        'titelbild_gesetzt' => 'Titelbild gesetzt', 'bild_entfernt' => 'Bild entfernt',
        'bild_vorgeschlagen' => 'Bild vorgeschlagen', 'bild_freigegeben' => 'Bild freigegeben', 'bild_abgelehnt' => 'Bildvorschlag abgelehnt',
        'bild_veraltet_gemeldet' => 'Bild als veraltet gemeldet', 'bild_veraltet_aufgehoben' => 'Veraltet-Meldung zurückgenommen', 'bild_markiert' => 'Bild markiert', 'hydranten_importiert' => 'Hydranten importiert', 'hydranten_exportiert' => 'Hydranten exportiert (CSV)',
        'hydranten_osm_exportiert' => 'Hydranten für OpenStreetMap exportiert', 'bilder_importiert' => 'Bilder importiert', 'osm_abgefragt' => 'OpenStreetMap-Datei eingelesen',
        'revision_erfasst' => 'Revision erfasst', 'maengel_exportiert' => 'Mängelübersicht exportiert',
        'tour_angelegt' => 'Tour angelegt', 'tour_geaendert' => 'Tour geändert', 'tour_geloescht' => 'Tour gelöscht',
        'hydranten_tour_zugewiesen' => 'Hydranten einer Tour zugewiesen', 'hydranten_aus_tour_entfernt' => 'Hydranten aus Tour entfernt',
        'tourlink_gesperrt' => 'Tour-Link gesperrt', 'tourlink_freigegeben' => 'Tour-Link freigegeben', 'tourlink_erneuert' => 'Tour-Link erneuert', 'tourlink_versendet' => 'Tour-Link versendet',
        'pruefdurchgang_angelegt' => 'Prüfdurchgang angelegt', 'pruefdurchgang_touren_geaendert' => 'Touren des Prüfdurchgangs geändert',
        'pruefdurchgang_beendet' => 'Prüfdurchgang beendet', 'pruefdurchgang_geoeffnet' => 'Prüfdurchgang wieder geöffnet',
        'benutzer_angelegt' => 'Benutzer angelegt', 'benutzer_geaendert' => 'Benutzer geändert', 'einladung_erzeugt' => 'Einladung erzeugt', 'reset_link_erzeugt' => 'Passwort-Link erzeugt',
        'hydranttypen_geaendert' => 'Hydrantentypen geändert', 'positionen_geaendert' => 'Positionen geändert', 'maengelkatalog_geaendert' => 'Mängelkatalog geändert',
        'einstellungen_geaendert' => 'Einstellungen geändert', 'datenbank_aktualisiert' => 'Datenbank aktualisiert',
        'protokoll_bereinigt' => 'Protokoll bereinigt', 'protokoll_exportiert' => 'Protokoll exportiert',
    ];

    /** Gruppen für den Filter: Schlüssel => [Bezeichnung, Aktionen] */
    public const GROUPS = [
        'fehler'    => ['Fehlgeschlagene Anmeldungen', ['login_fehlgeschlagen']],
        'anmeldung' => ['Anmeldung und Passwort', ['login', 'logout', 'login_fehlgeschlagen', 'passwort_reset_angefordert', 'passwort_gesetzt', 'passwort_geaendert']],
        'hydranten' => ['Hydranten, Bilder, Import und Export', ['hydrant_angelegt', 'hydrant_geaendert', 'hydrant_aktiviert', 'hydrant_deaktiviert', 'hydrant_tour_geaendert', 'bilder_hochgeladen', 'bild_beschriftet', 'titelbild_gesetzt', 'bild_entfernt', 'bild_vorgeschlagen', 'bild_freigegeben', 'bild_abgelehnt', 'bild_veraltet_gemeldet', 'bild_veraltet_aufgehoben', 'bild_markiert', 'hydranten_importiert', 'hydranten_exportiert', 'hydranten_osm_exportiert', 'bilder_importiert', 'osm_abgefragt']],
        'revisionen' => ['Revisionen und Mängel', ['revision_erfasst', 'maengel_exportiert']],
        'touren'    => ['Touren und Prüfdurchgänge', ['tour_angelegt', 'tour_geaendert', 'tour_geloescht', 'hydranten_tour_zugewiesen', 'hydranten_aus_tour_entfernt', 'tourlink_gesperrt', 'tourlink_freigegeben', 'tourlink_erneuert', 'tourlink_versendet', 'pruefdurchgang_angelegt', 'pruefdurchgang_touren_geaendert', 'pruefdurchgang_beendet', 'pruefdurchgang_geoeffnet']],
        'benutzer'  => ['Benutzer', ['benutzer_angelegt', 'benutzer_geaendert', 'einladung_erzeugt', 'reset_link_erzeugt']],
        'stammdaten' => ['Stammdaten', ['hydranttypen_geaendert', 'positionen_geaendert', 'maengelkatalog_geaendert']],
        'system'    => ['Einstellungen und System', ['einstellungen_geaendert', 'datenbank_aktualisiert', 'protokoll_bereinigt', 'protokoll_exportiert']],
    ];

    /** Bezeichnung der Objektarten, wenn kein einzelnes Objekt verknüpft ist */
    private const TYPE_LABELS = [
        'hydrant' => 'Hydranten', 'tour' => 'Touren', 'campaign' => 'Prüfdurchgang', 'user' => 'Benutzer', 'settings' => 'Einstellungen',
        'defect_types' => 'Mängelkatalog', 'hydrant_types' => 'Hydrantentypen', 'positions' => 'Positionen', 'revision' => 'Mängelübersicht',
        'system' => 'System', 'audit' => 'Protokoll',
    ];

    public static function label(string $action): string
    {
        return self::LABELS[$action] ?? $action;
    }

    /** Filter aus Adressparametern prüfen. @return array{von:string,bis:string,gruppe:string,benutzer:string,q:string,objekt:string,seite:int} */
    public static function parse(array $src): array
    {
        $get = static function (string $k) use ($src): string {
            $v = $src[$k] ?? '';
            return is_string($v) ? trim($v) : '';
        };
        $date = static function (string $v): string {
            $d = DateTime::createFromFormat('!Y-m-d', $v);
            return $d !== false && $d->format('Y-m-d') === $v ? $v : '';
        };
        $f = [
            'von' => $date($get('von')), 'bis' => $date($get('bis')),
            'gruppe' => isset(self::GROUPS[$get('gruppe')]) ? $get('gruppe') : '',
            'benutzer' => mb_substr($get('benutzer'), 0, 190),
            'q' => mb_substr($get('q'), 0, 100),
            'objekt' => preg_match('/^[a-z_]{1,40}:\d{1,10}$/', $get('objekt')) === 1 ? $get('objekt') : '',
            'seite' => max(1, (int) $get('seite')),
        ];
        return $f;
    }

    /** @return array<string,string> gesetzte Filter für Adressen (ohne Seite) */
    public static function toQuery(array $f): array
    {
        $q = [];
        foreach (['von', 'bis', 'gruppe', 'benutzer', 'q', 'objekt'] as $k) {
            if ($f[$k] !== '') {
                $q[$k] = $f[$k];
            }
        }
        return $q;
    }

    /** @return array{0:string,1:array<int,mixed>} */
    private static function where(array $f): array
    {
        $w = [];
        $p = [];
        if ($f['von'] !== '') {
            $w[] = 'created_at >= ?';
            $p[] = $f['von'] . ' 00:00:00';
        }
        if ($f['bis'] !== '') {
            $w[] = 'created_at <= ?';
            $p[] = $f['bis'] . ' 23:59:59';
        }
        if ($f['gruppe'] !== '') {
            $acts = self::GROUPS[$f['gruppe']][1];
            $w[] = 'action IN (' . implode(',', array_fill(0, count($acts), '?')) . ')';
            array_push($p, ...$acts);
        }
        if ($f['benutzer'] !== '') {
            $w[] = 'user_label = ?';
            $p[] = $f['benutzer'];
        }
        if ($f['objekt'] !== '') {
            [$t, $id] = explode(':', $f['objekt']);
            $w[] = 'object_type = ? AND object_id = ?';
            array_push($p, $t, (int) $id);
        }
        if ($f['q'] !== '') {
            $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $f['q']) . '%';
            $w[] = "(details LIKE ? ESCAPE '!' OR user_label LIKE ? ESCAPE '!')";
            array_push($p, $like, $like);
        }
        return [$w ? ' WHERE ' . implode(' AND ', $w) : '', $p];
    }

    public static function count(array $f): int
    {
        [$w, $p] = self::where($f);
        return (int) Db::val('SELECT COUNT(*) FROM {audit_log}' . $w, $p);
    }

    /** @return array<int, array<string, mixed>> neueste zuerst */
    public static function rows(array $f, int $limit, int $offset = 0): array
    {
        [$w, $p] = self::where($f);
        return Db::all('SELECT id, created_at, user_id, user_label, action, object_type, object_id, details FROM {audit_log}' . $w . ' ORDER BY id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset, $p);
    }

    /** @return string[] Benutzerkennungen, die im Protokoll vorkommen */
    public static function users(): array
    {
        return array_map(static fn ($r) => (string) $r['user_label'], Db::all('SELECT DISTINCT user_label FROM {audit_log} ORDER BY user_label LIMIT 300'));
    }

    /**
     * Bezeichnung und Verweis des betroffenen Objekts je Zeile.
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array{text:string, href:?string}> Schlüssel = Zeilen-ID
     */
    public static function objects(array $rows): array
    {
        $ids = ['hydrant' => [], 'tour' => [], 'campaign' => [], 'user' => []];
        foreach ($rows as $r) {
            $t = (string) $r['object_type'];
            if ($r['object_id'] !== null && isset($ids[$t]) && !str_starts_with((string) $r['action'], 'tourlink_')) {
                $ids[$t][(int) $r['object_id']] = true;
            }
        }
        $names = [];
        $sources = ['hydrant' => ['hydrants', 'kennung'], 'tour' => ['tours', 'name'], 'campaign' => ['campaigns', 'name'], 'user' => ['users', 'name']];
        foreach ($sources as $type => [$table, $col]) {
            if ($ids[$type]) {
                $in = implode(',', array_map('intval', array_keys($ids[$type])));
                foreach (Db::all('SELECT id, ' . $col . ' AS n FROM {' . $table . '} WHERE id IN (' . $in . ')') as $x) {
                    $names[$type][(int) $x['id']] = (string) $x['n'];
                }
            }
        }
        $paths = ['hydrant' => 'hydranten/', 'tour' => 'touren/', 'campaign' => 'pruefungen/', 'user' => 'admin/benutzer/'];
        $kinds = ['hydrant' => 'Hydrant', 'tour' => 'Tour', 'campaign' => 'Prüfdurchgang', 'user' => 'Benutzer'];
        $out = [];
        foreach ($rows as $r) {
            $t = (string) $r['object_type'];
            $text = self::TYPE_LABELS[$t] ?? $t;
            $href = null;
            if (str_starts_with((string) $r['action'], 'tourlink_')) {
                $text = 'Tour-Link';
            } elseif ($r['object_id'] !== null && isset($kinds[$t])) {
                $id = (int) $r['object_id'];
                if (isset($names[$t][$id])) {
                    $text = $kinds[$t] . ' ' . $names[$t][$id];
                    $href = url($paths[$t] . $id);
                } else {
                    $text = $kinds[$t] . ' #' . $id . ' (nicht mehr vorhanden)';
                }
            }
            $out[(int) $r['id']] = ['text' => $text, 'href' => $href];
        }
        return $out;
    }

    /** CSV aller gefilterten Einträge (neueste zuerst, höchstens MAX_EXPORT). */
    public static function csv(array $f): string
    {
        $rows = self::rows($f, self::MAX_EXPORT);
        $obj = self::objects($rows);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [(string) $r['created_at'], (string) $r['user_label'], self::label((string) $r['action']), $obj[(int) $r['id']]['text'], (string) ($r['details'] ?? '')];
        }
        return Csv::write(['Zeit', 'Benutzer', 'Aktion', 'Objekt', 'Details'], $out);
    }

    // ---- Aufbewahrung ------------------------------------------------------------

    public static function retentionMonths(): int
    {
        $m = (int) Settings::get('audit_retention_months');
        return isset(self::RETENTION[$m]) ? $m : 0;
    }

    /** Alte Einträge löschen, höchstens einmal je Tag. */
    public static function cleanup(): void
    {
        $months = self::retentionMonths();
        $today = date('Y-m-d');
        if ($months === 0 || Settings::get('audit_cleanup_last') === $today) {
            return;
        }
        try {
            Settings::set('audit_cleanup_last', $today);
            $limit = date('Y-m-d H:i:s', (int) strtotime('-' . $months . ' months'));
            $n = (int) Db::val('SELECT COUNT(*) FROM {audit_log} WHERE created_at < ?', [$limit]);
            if ($n > 0) {
                Db::exec('DELETE FROM {audit_log} WHERE created_at < ?', [$limit]);
                Audit::log('protokoll_bereinigt', 'audit', null, $n . ' Einträge älter als ' . $months . ' Monate gelöscht', 'System');
            }
        } catch (\Throwable $e) {
            error_log('Hydrantenverwaltung: Protokoll-Bereinigung fehlgeschlagen: ' . $e->getMessage());
        }
    }
}
