<?php
declare(strict_types=1);

final class Installer
{
    public const DEFECTS = [
        'nicht auffindbar',
        'Beschilderung defekt/fehlt',
        'Umpflasterung fehlt',
        'Freischneiden',
        'Straßenkappe zu tief / zu hoch',
        'Straßenkappendeckel fehlt',
        'Steg Straßenkappe abgebrochen',
        'Straßendeckel nicht zu öffnen',
        'Bolzen fehlt',
        'Innendeckel defekt / fehlt',
        'Kette defekt / fehlt',
        'schwergängig',
        'entwässert nicht',
    ];

    /** Mängel, die für alle Typen gelten (die ersten vier). Alle übrigen gelten für Unterflur und Überflur. */
    public const DEFECTS_ALL_TYPES = 4;

    /** Trägt die voreingestellten Typen und Positionen ein, soweit noch nicht vorhanden. */
    public static function seedLists(?PDO $pdo = null): void
    {
        $pdo ??= Db::pdo();
        $has = $pdo->prepare(Db::sql('SELECT COUNT(*) FROM {hydrant_types} WHERE schluessel = ?'));
        $ins = $pdo->prepare(Db::sql('INSERT INTO {hydrant_types} (schluessel, name, symbol, osm_wert, sortierung, aktiv) VALUES (?, ?, ?, ?, ?, 1)'));
        $i = 0;
        foreach (HydrantType::DEFAULTS as $key => [$name, $symbol]) {
            $i += 10;
            $has->execute([$key]);
            if ((int) $has->fetchColumn() === 0) {
                $ins->execute([$key, $name, $symbol, HydrantType::OSM_DEFAULTS[$key] ?? '', $i]);
            }
        }
        $has = $pdo->prepare(Db::sql('SELECT COUNT(*) FROM {positions} WHERE schluessel = ?'));
        $ins = $pdo->prepare(Db::sql('INSERT INTO {positions} (schluessel, name, osm_wert, sortierung, aktiv) VALUES (?, ?, ?, ?, 1)'));
        $i = 0;
        foreach (HydrantPosition::DEFAULTS as $key => $name) {
            $i += 10;
            $has->execute([$key]);
            if ((int) $has->fetchColumn() === 0) {
                $ins->execute([$key, $name, HydrantPosition::OSM_DEFAULTS[$key] ?? '', $i]);
            }
        }
    }

    /** @return array<int, array{label:string, ok:bool, required:bool, note:string}> */
    public static function requirements(): array
    {
        $rows = [];
        $add = static function (string $label, bool $ok, bool $required, string $note = '') use (&$rows): void {
            $rows[] = ['label' => $label, 'ok' => $ok, 'required' => $required, 'note' => $note];
        };
        $add('PHP-Version ' . PHP_VERSION . ' (mindestens 8.1)', PHP_VERSION_ID >= 80100, true);
        $add('PDO MySQL', extension_loaded('pdo_mysql'), true, 'Für die Datenbank erforderlich.');
        $add('mbstring', extension_loaded('mbstring'), true);
        $add('OpenSSL', extension_loaded('openssl'), true, 'Für verschlüsselte Einstellungen und E-Mail-Versand.');
        $add('GD (Bildverarbeitung)', extension_loaded('gd'), true, 'Für Bild-Upload und Verkleinerung erforderlich.');
        $add('fileinfo', extension_loaded('fileinfo'), false);
        $add('Ordner storage/ beschreibbar', is_writable(STORAGE), true, 'Dort liegen die Konfiguration, Bilder, Logo und Sitzungen.');
        $add('Verbindung über HTTPS', is_https(), false, 'Dringend empfohlen, sonst werden Passwörter unverschlüsselt übertragen.');
        return $rows;
    }

    public static function requirementsOk(): bool
    {
        foreach (self::requirements() as $r) {
            if ($r['required'] && !$r['ok']) {
                return false;
            }
        }
        return true;
    }

    /**
     * Fuehrt die Installation durch.
     * @return string[] Fehlermeldungen (leer bei Erfolg)
     */
    public static function run(array $in): array
    {
        $errors = [];
        // SQLite ist nur für automatische Tests gedacht und im Browser-Installer nicht erreichbar.
        $driver = ($in['db_driver'] ?? 'mysql') === 'sqlite' && defined('HYD_ALLOW_SQLITE') ? 'sqlite' : 'mysql';

        $appName = trim((string) ($in['app_name'] ?? ''));
        $adminName = trim((string) ($in['admin_name'] ?? ''));
        $adminEmail = mb_strtolower(trim((string) ($in['admin_email'] ?? '')));
        $adminPass = (string) ($in['admin_pass'] ?? '');
        $baseUrl = rtrim(trim((string) ($in['base_url'] ?? '')), '/');

        if ($appName === '' || mb_strlen($appName) > 60) {
            $errors[] = 'Bitte einen Namen für die Anwendung angeben (höchstens 60 Zeichen).';
        }
        if ($adminName === '' || mb_strlen($adminName) > 120) {
            $errors[] = 'Bitte den Namen des Administrators angeben.';
        }
        if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Bitte eine gültige E-Mail-Adresse für den Administrator angeben.';
        }
        if (($p = Auth::passwordProblem($adminPass, $adminEmail)) !== null) {
            $errors[] = $p;
        } elseif ($adminPass !== (string) ($in['admin_pass2'] ?? '')) {
            $errors[] = 'Die beiden Passwörter stimmen nicht überein.';
        }
        if (preg_match('#^https?://[^\s/]+(/[^\s]*)?$#i', $baseUrl) !== 1) {
            $errors[] = 'Die Basis-URL muss mit http:// oder https:// beginnen.';
        }

        // Neue, leere Datenbank: Tabellen ohne Präfix. Bestehende Datenbank: Präfix ist Pflicht.
        $existingDb = ($in['db_mode'] ?? 'new') === 'existing';
        $prefix = $existingDb ? trim((string) ($in['db_prefix'] ?? '')) : '';
        if ($existingDb && $prefix === '') {
            $errors[] = 'Für eine bestehende Datenbank ist ein Tabellen-Präfix nötig (z. B. hyd_).';
        } elseif (!Db::validPrefix($prefix)) {
            $errors[] = 'Das Tabellen-Präfix darf nur Buchstaben, Ziffern und Unterstriche enthalten (höchstens 20 Zeichen).';
        }

        $db = [
            'driver' => $driver,
            'host'   => trim((string) ($in['db_host'] ?? 'localhost')),
            'port'   => (int) (($in['db_port'] ?? '') !== '' ? $in['db_port'] : 3306),
            'name'   => trim((string) ($in['db_name'] ?? '')),
            'user'   => trim((string) ($in['db_user'] ?? '')),
            'pass'   => (string) ($in['db_pass'] ?? ''),
            'file'   => (string) ($in['db_file'] ?? ''),
        ];
        if ($driver === 'mysql' && ($db['host'] === '' || $db['name'] === '' || $db['user'] === '')) {
            $errors[] = 'Bitte Datenbank-Server, Datenbankname und Benutzer angeben.';
        }
        if (!self::requirementsOk()) {
            $errors[] = 'Nicht alle Voraussetzungen sind erfüllt (siehe oben).';
        }
        if (Config::installed()) {
            $errors[] = 'Die Anwendung ist bereits installiert.';
        }
        if ($errors) {
            return $errors;
        }

        // ---- Datenbank ------------------------------------------------------
        try {
            $pdo = Db::connect($db);
        } catch (\Throwable $e) {
            return ['Die Datenbankverbindung ist fehlgeschlagen: ' . $e->getMessage()];
        }
        // Nur die eigenen Tabellen (mit Präfix) zählen. Andere Tabellen in der Datenbank bleiben unberührt.
        Db::setPrefix($prefix);
        $existing = [];
        foreach (Db::ownTables($driver) as $table) {
            if (Db::tableExists($pdo, $driver, $table)) {
                $existing[] = $table;
            }
        }
        if ($existing) {
            return [$existingDb
                ? 'In der Datenbank gibt es bereits Tabellen mit diesem Präfix (z. B. ' . $existing[0] . '). Bitte ein anderes Präfix wählen.'
                : 'Die Datenbank ist nicht leer: Es gibt bereits eine Tabelle „' . $existing[0] . '“. '
                    . 'Bitte „Bestehende Datenbank mitbenutzen“ mit einem Präfix wählen oder eine leere Datenbank verwenden.'];
        }

        $appKey = base64_encode(random_bytes(32));
        $config = [
            'db_driver'   => $driver,
            'db_host'     => $db['host'],
            'db_port'     => $db['port'],
            'db_name'     => $db['name'],
            'db_user'     => $db['user'],
            'db_pass'     => $db['pass'],
            'db_file'     => $db['file'],
            'db_prefix'   => $prefix,
            'base_url'    => $baseUrl,
            'app_key'     => $appKey,
            'pretty_urls' => !empty($in['pretty_urls']),
            // HTTPS wird nur erzwungen, wenn die Basis-URL mit https:// beginnt (sonst droht eine Weiterleitungsschleife).
            'force_https' => str_starts_with($baseUrl, 'https://'),
            'timezone'    => 'Europe/Berlin',
            'debug'       => false,
        ];

        try {
            Db::runSchema($pdo, $driver);

            $now = now();
            $pdo->prepare(Db::sql('INSERT INTO {users} (name, email, password_hash, rolle, aktiv, created_at) VALUES (?, ?, ?, ?, 1, ?)'))
                ->execute([$adminName, $adminEmail, password_hash($adminPass, PASSWORD_DEFAULT), 'admin', $now]);

            $set = $pdo->prepare(Db::sql('INSERT INTO {settings} (skey, svalue) VALUES (?, ?)'));
            $set->execute(['app_name', $appName]);
            foreach (['color_primary', 'color_header', 'color_bg', 'show_pressure', 'show_flow', 'show_position'] as $k) {
                $set->execute([$k, Settings::DEFAULTS[$k]]);
            }
            $set->execute(['schema_version', (string) Migrator::LATEST]);

            self::seedLists($pdo);

            $def = $pdo->prepare(Db::sql('INSERT INTO {defect_types} (name, sortierung, aktiv, gilt_fuer) VALUES (?, ?, 1, ?)'));
            foreach (self::DEFECTS as $i => $name) {
                $def->execute([$name, ($i + 1) * 10, $i < self::DEFECTS_ALL_TYPES ? 'unterflur,ueberflur,saugbrunnen' : 'unterflur,ueberflur']);
            }
        } catch (\Throwable $e) {
            Db::dropSchema($pdo, $driver);
            return ['Die Datenbank konnte nicht eingerichtet werden: ' . $e->getMessage()];
        }

        // ---- Ordner und Konfiguration --------------------------------------
        foreach (['images', 'thumbs', 'branding', 'import', 'tmp', 'sessions'] as $dir) {
            $path = STORAGE . '/' . $dir;
            if (!is_dir($path) && !@mkdir($path, 0755, true)) {
                Db::dropSchema($pdo, $driver);
                return ['Der Ordner storage/' . $dir . ' konnte nicht angelegt werden. Bitte Schreibrechte prüfen.'];
            }
        }

        $php = "<?php\nif (!defined('SRC')) { http_response_code(403); exit; }\nreturn " . var_export($config, true) . ";\n";
        if (@file_put_contents(Config::file(), $php, LOCK_EX) === false) {
            Db::dropSchema($pdo, $driver);
            return ['Die Datei storage/config.php konnte nicht geschrieben werden. Bitte Schreibrechte prüfen.'];
        }
        @chmod(Config::file(), 0640);

        return [];
    }
}
