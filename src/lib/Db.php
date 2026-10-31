<?php
declare(strict_types=1);

final class Db
{
    private static ?PDO $pdo = null;
    private static ?string $prefix = null;

    /** Präfix für alle Tabellennamen (z. B. "hyd_"), damit die Datenbank mit anderen Anwendungen geteilt werden kann. */
    public static function prefix(): string
    {
        return self::$prefix ?? (string) Config::get('db_prefix', '');
    }

    public static function setPrefix(string $prefix): void
    {
        self::$prefix = $prefix;
    }

    public static function validPrefix(string $prefix): bool
    {
        return preg_match('/^[A-Za-z0-9_]{0,20}$/', $prefix) === 1;
    }

    /** Ersetzt {name} durch Präfix + name (Tabellen- und Indexnamen). */
    public static function sql(string $sql): string
    {
        $prefix = self::prefix();
        return (string) preg_replace_callback('/\{(\w+)\}/', static fn (array $m): string => $prefix . $m[1], $sql);
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = self::connect([
                'driver' => Config::get('db_driver', 'mysql'),
                'host'   => Config::get('db_host', 'localhost'),
                'port'   => Config::get('db_port', 3306),
                'name'   => Config::get('db_name', ''),
                'user'   => Config::get('db_user', ''),
                'pass'   => Config::get('db_pass', ''),
                'file'   => Config::get('db_file', ''),
            ]);
        }
        return self::$pdo;
    }

    public static function connect(array $p): PDO
    {
        $opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        if (($p['driver'] ?? 'mysql') === 'sqlite') {
            $pdo = new PDO('sqlite:' . $p['file'], null, null, $opts);
            $pdo->exec('PRAGMA foreign_keys = ON');
            return $pdo;
        }
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $p['host'],
            (int) $p['port'],
            $p['name']
        );
        return new PDO($dsn, (string) $p['user'], (string) $p['pass'], $opts);
    }

    public static function all(string $sql, array $params = []): array
    {
        $st = self::pdo()->prepare(self::sql($sql));
        $st->execute($params);
        return $st->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $st = self::pdo()->prepare(self::sql($sql));
        $st->execute($params);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    public static function val(string $sql, array $params = []): mixed
    {
        $st = self::pdo()->prepare(self::sql($sql));
        $st->execute($params);
        $v = $st->fetchColumn();
        return $v === false ? null : $v;
    }

    public static function exec(string $sql, array $params = []): int
    {
        $st = self::pdo()->prepare(self::sql($sql));
        $st->execute($params);
        return $st->rowCount();
    }

    public static function insert(string $sql, array $params = []): int
    {
        $st = self::pdo()->prepare(self::sql($sql));
        $st->execute($params);
        return (int) self::pdo()->lastInsertId();
    }

    // ---- Schema (Installer) -------------------------------------------------

    public static function tableExists(PDO $pdo, string $driver, string $table): bool
    {
        if ($driver === 'sqlite') {
            $st = $pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = ?");
        } else {
            $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        }
        $st->execute([$table]);
        return (int) $st->fetchColumn() > 0;
    }

    public static function columnExists(string $table, string $column): bool
    {
        $pdo = self::pdo();
        $name = self::prefix() . $table;
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            foreach ($pdo->query('PRAGMA table_info(' . $name . ')') ?: [] as $row) {
                if (strcasecmp((string) $row['name'], $column) === 0) {
                    return true;
                }
            }
            return false;
        }
        $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
        $st->execute([$name, $column]);
        return (int) $st->fetchColumn() > 0;
    }

    /** @return string[] Statements des Schemas, ggf. fuer SQLite umgeschrieben. */
    public static function schemaStatements(string $driver): array
    {
        $sql = (string) file_get_contents(SRC . '/schema.sql');
        $sql = (string) preg_replace('/^--.*$/m', '', $sql);
        $sql = self::sql($sql);
        if ($driver === 'sqlite') {
            $sql = (string) preg_replace('/\)\s*ENGINE=[^;]*;/i', ');', $sql);
            $sql = str_ireplace('AUTO_INCREMENT', 'AUTOINCREMENT', $sql);
        }
        $out = [];
        foreach (preg_split('/;\s*\n/', $sql) as $stmt) {
            $stmt = trim((string) $stmt, " \t\r\n;");
            if ($stmt !== '') {
                $out[] = $stmt;
            }
        }
        return $out;
    }

    /** @return string[] Namen aller Tabellen, die die Anwendung anlegt (mit Präfix). */
    public static function ownTables(string $driver): array
    {
        $tables = [];
        foreach (self::schemaStatements($driver) as $stmt) {
            if (preg_match('/^CREATE TABLE (\w+)/i', $stmt, $m)) {
                $tables[] = $m[1];
            }
        }
        return $tables;
    }

    public static function runSchema(PDO $pdo, string $driver): void
    {
        foreach (self::schemaStatements($driver) as $stmt) {
            $pdo->exec($stmt);
        }
    }

    /** Raeumt nach einem fehlgeschlagenen Installationslauf die angelegten Tabellen wieder auf. */
    public static function dropSchema(PDO $pdo, string $driver): void
    {
        $tables = self::ownTables($driver);
        try {
            $pdo->exec($driver === 'sqlite' ? 'PRAGMA foreign_keys = OFF' : 'SET FOREIGN_KEY_CHECKS = 0');
            foreach (array_reverse($tables) as $t) {
                $pdo->exec('DROP TABLE IF EXISTS ' . $t);
            }
            $pdo->exec($driver === 'sqlite' ? 'PRAGMA foreign_keys = ON' : 'SET FOREIGN_KEY_CHECKS = 1');
        } catch (\Throwable $e) {
            error_log('Hydrantenverwaltung: Aufräumen fehlgeschlagen: ' . $e->getMessage());
        }
    }
}
