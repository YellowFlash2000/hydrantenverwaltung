<?php
declare(strict_types=1);

/**
 * Datenbank-Updates. Nach dem Hochladen einer neuen Programmversion werden fehlende Änderungen
 * beim ersten Aufruf automatisch ausgeführt. Jede Änderung prüft selbst, ob sie schon vorhanden ist.
 */
final class Migrator
{
    /** Aktuelle Version des Datenbankschemas. */
    public const LATEST = 7;

    /** @return ?string Fehlermeldung oder null */
    public static function run(): ?string
    {
        try {
            $current = (int) Settings::get('schema_version');
            if ($current >= self::LATEST) {
                return null;
            }
            if ($current < 2) {
                self::to2();
            }
            if ($current < 3) {
                self::to3();
            }
            if ($current < 4) {
                self::to4();
            }
            if ($current < 5) {
                self::to5();
            }
            if ($current < 6) {
                self::to6();
            }
            if ($current < 7) {
                self::to7();
            }
            Settings::set('schema_version', (string) self::LATEST);
            Audit::log('datenbank_aktualisiert', 'system', null, 'Schema Version ' . $current . ' → ' . self::LATEST, 'System');
            return null;
        } catch (\Throwable $e) {
            error_log('Hydrantenverwaltung: Datenbank-Update fehlgeschlagen: ' . $e->getMessage());
            return $e->getMessage();
        }
    }

    /** Version 2: Durchfluss mit Vergleichszeichen, Mängelkatalog je Hydrantentyp. */
    private static function to2(): void
    {
        if (!Db::columnExists('hydrants', 'durchfluss_zeichen')) {
            Db::exec('ALTER TABLE {hydrants} ADD COLUMN durchfluss_zeichen VARCHAR(1) NULL');
        }
        if (!Db::columnExists('defect_types', 'gilt_fuer')) {
            Db::exec('ALTER TABLE {defect_types} ADD COLUMN gilt_fuer VARCHAR(80) NULL');
            foreach (Installer::DEFECTS as $i => $name) {
                Db::exec(
                    'UPDATE {defect_types} SET gilt_fuer = ? WHERE name = ?',
                    [$i < Installer::DEFECTS_ALL_TYPES ? 'unterflur,ueberflur,saugbrunnen' : 'unterflur,ueberflur', $name]
                );
            }
            // selbst angelegte Mängel: für Unterflur und Überflur
            Db::exec("UPDATE {defect_types} SET gilt_fuer = 'unterflur,ueberflur' WHERE gilt_fuer IS NULL");
        }
    }

    /** Legt fehlende Tabellen (und deren Indizes) aus schema.sql an. @param string[] $names Tabellennamen ohne Präfix */
    private static function createTables(array $names): void
    {
        $pdo = Db::pdo();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'sqlite' : 'mysql';
        $prefix = Db::prefix();
        $created = [];
        foreach (Db::schemaStatements($driver) as $stmt) {
            if (preg_match('/^CREATE TABLE (\w+)/i', $stmt, $m) === 1) {
                $short = substr($m[1], strlen($prefix));
                if (in_array($short, $names, true) && !Db::tableExists($pdo, $driver, $m[1])) {
                    $pdo->exec($stmt);
                    $created[$m[1]] = true;
                }
            } elseif (preg_match('/^CREATE (?:UNIQUE )?INDEX \w+ ON (\w+)/i', $stmt, $m) === 1 && isset($created[$m[1]])) {
                $pdo->exec($stmt);
            }
        }
    }

    /**
     * Version 3: Touren sind dauerhaft. Ein Prüfdurchgang wählt seine Touren über campaign_tours, der Link gehört zu Durchgang und Tour.
     * Bisherige Touren gleichen Namens aus verschiedenen Durchgängen werden zu einer Tour zusammengeführt.
     */
    private static function to3(): void
    {
        self::createTables(['campaign_tours']);
        if (!Db::columnExists('tours', 'campaign_id')) {
            return; // neue Installation
        }
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            $canon = [];
            $map = [];
            $rows = Db::all('SELECT * FROM {tours} ORDER BY id');
            foreach ($rows as $t) {
                $name = (string) $t['name'];
                if (!isset($canon[$name])) {
                    $canon[$name] = (int) $t['id'];
                }
                $map[(int) $t['id']] = $canon[$name];
            }
            foreach ($rows as $t) {
                if ($t['campaign_id'] === null) {
                    continue;
                }
                $tid = $map[(int) $t['id']];
                $exists = (int) Db::val('SELECT COUNT(*) FROM {campaign_tours} WHERE campaign_id = ? AND tour_id = ?', [(int) $t['campaign_id'], $tid]);
                $tokenTaken = (int) Db::val('SELECT COUNT(*) FROM {campaign_tours} WHERE token = ?', [(string) $t['token']]);
                if ($exists === 0 && $tokenTaken === 0) {
                    Db::exec(
                        'INSERT INTO {campaign_tours} (campaign_id, tour_id, token, link_aktiv, created_at) VALUES (?, ?, ?, ?, ?)',
                        [(int) $t['campaign_id'], $tid, (string) $t['token'], (int) $t['link_aktiv'], (string) $t['created_at']]
                    );
                }
            }
            foreach ($map as $old => $new) {
                if ($old === $new) {
                    continue;
                }
                Db::exec('UPDATE {hydrants} SET tour_id = ? WHERE tour_id = ?', [$new, $old]);
                Db::exec('UPDATE {revisions} SET tour_id = ? WHERE tour_id = ?', [$new, $old]);
                Db::exec('UPDATE {tour_outings} SET tour_id = ? WHERE tour_id = ?', [$new, $old]);
                Db::exec('DELETE FROM {tours} WHERE id = ?', [$old]);
            }
            // Die alten Spalten bleiben (ohne Bedeutung) erhalten; die Links der Touren stehen jetzt in campaign_tours.
            foreach ($canon as $id) {
                Db::exec('UPDATE {tours} SET campaign_id = NULL, token = ?, link_aktiv = 0 WHERE id = ?', [bin2hex(random_bytes(32)), $id]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Version 4: Hydrantentypen (mit Symbol) und Positionen als pflegbare Listen. */
    private static function to4(): void
    {
        self::createTables(['hydrant_types', 'positions']);
        Installer::seedLists();
        // Schlüssel, die in Hydranten vorkommen, aber nicht zu den Voreinstellungen gehören, bleiben als Eintrag erhalten
        foreach (Db::all('SELECT DISTINCT typ FROM {hydrants} WHERE typ IS NOT NULL') as $r) {
            if ((int) Db::val('SELECT COUNT(*) FROM {hydrant_types} WHERE schluessel = ?', [(string) $r['typ']]) === 0) {
                Db::exec('INSERT INTO {hydrant_types} (schluessel, name, symbol, sortierung, aktiv) VALUES (?, ?, ?, 100, 1)', [(string) $r['typ'], (string) $r['typ'], 'kreis']);
            }
        }
        foreach (Db::all('SELECT DISTINCT position_art FROM {hydrants} WHERE position_art IS NOT NULL AND position_art <> ?', ['']) as $r) {
            if ((int) Db::val('SELECT COUNT(*) FROM {positions} WHERE schluessel = ?', [(string) $r['position_art']]) === 0) {
                Db::exec('INSERT INTO {positions} (schluessel, name, sortierung, aktiv) VALUES (?, ?, 100, 1)', [(string) $r['position_art'], (string) $r['position_art']]);
            }
        }
    }

    /** Version 5: OSM-Wert je Typ und Position (für den OpenStreetMap-Export). */
    private static function to5(): void
    {
        foreach (['hydrant_types' => HydrantType::OSM_DEFAULTS, 'positions' => HydrantPosition::OSM_DEFAULTS] as $table => $defaults) {
            if (!Db::columnExists($table, 'osm_wert')) {
                Db::exec('ALTER TABLE {' . $table . '} ADD COLUMN osm_wert VARCHAR(30) NULL');
            }
            foreach ($defaults as $key => $value) {
                Db::exec('UPDATE {' . $table . '} SET osm_wert = ? WHERE schluessel = ? AND osm_wert IS NULL', [$value, $key]);
            }
            Db::exec('UPDATE {' . $table . '} SET osm_wert = ? WHERE osm_wert IS NULL', ['']);
        }
        HydrantType::reset();
        HydrantPosition::reset();
    }

    /** Version 6: Sechseck-Symbol entfällt (wird zur Raute); Saugbrunnen bekommt den OSM-Wert pipe. */
    private static function to6(): void
    {
        Db::exec("UPDATE {hydrant_types} SET symbol = 'raute' WHERE symbol = 'sechseck'");
        Db::exec("UPDATE {hydrant_types} SET osm_wert = 'pipe' WHERE schluessel = 'saugbrunnen' AND (osm_wert IS NULL OR osm_wert = '')");
        HydrantType::reset();
    }

    /** Version 7: Bildvorschläge mit Freigabe, Veraltet-Meldung und Markierung am Hydrantenbild. */
    private static function to7(): void
    {
        $cols = [
            'status'            => "VARCHAR(10) NOT NULL DEFAULT 'ok'",
            'quelle'            => "VARCHAR(10) NOT NULL DEFAULT 'app'",
            'outing_id'         => 'INTEGER NULL',
            'ersetzt_id'        => 'INTEGER NULL',
            'vorgeschlagen_von' => 'VARCHAR(255) NULL',
            'veraltet_at'       => 'DATETIME NULL',
            'veraltet_von'      => 'VARCHAR(255) NULL',
            'marker_x'          => 'DECIMAL(6,5) NULL',
            'marker_y'          => 'DECIMAL(6,5) NULL',
            'marker_rx'         => 'DECIMAL(6,5) NULL',
            'marker_ry'         => 'DECIMAL(6,5) NULL',
        ];
        foreach ($cols as $name => $def) {
            if (!Db::columnExists('hydrant_images', $name)) {
                Db::exec('ALTER TABLE {hydrant_images} ADD COLUMN ' . $name . ' ' . $def);
            }
        }
    }
}
