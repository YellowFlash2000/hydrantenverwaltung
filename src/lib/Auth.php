<?php
declare(strict_types=1);

final class Auth
{
    public const ROLES = ['viewer' => 1, 'reviser' => 2, 'editor' => 3, 'admin' => 4];

    public const ROLE_LABELS = [
        'viewer'  => 'Betrachter',
        'reviser' => 'Revisor',
        'editor'  => 'Bearbeiter',
        'admin'   => 'Admin',
    ];

    public const ROLE_HELP = [
        'viewer'  => 'Hydranten ansehen',
        'reviser' => 'zusätzlich Revisionsdaten eingeben',
        'editor'  => 'zusätzlich Hydranten anlegen, bearbeiten, deaktivieren sowie Touren und Prüfdurchgänge verwalten',
        'admin'   => 'zusätzlich Benutzer, Einstellungen, Import/Export und Protokoll',
    ];

    private static ?array $user = null;
    private static bool $loaded = false;

    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = (int) ($_SESSION['uid'] ?? 0);
            if ($id > 0) {
                $u = Db::one('SELECT id, name, email, rolle, aktiv, auth_epoch FROM {users} WHERE id = ?', [$id]);
                // Die Sitzung gilt nur, solange das Konto aktiv ist und seit der Anmeldung kein Passwort geändert wurde.
                if ($u !== null && (int) $u['aktiv'] === 1 && (int) ($_SESSION['epoch'] ?? 0) === (int) $u['auth_epoch']) {
                    self::$user = $u;
                } else {
                    $_SESSION = [];
                }
            }
        }
        return self::$user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function hasRole(string $min): bool
    {
        $u = self::user();
        if ($u === null) {
            return false;
        }
        return (self::ROLES[$u['rolle']] ?? 0) >= (self::ROLES[$min] ?? 99);
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $user['id'];
        $_SESSION['epoch'] = (int) ($user['auth_epoch'] ?? 1);
        $_SESSION['last'] = time();
        self::$loaded = false;
        self::$user = null;
        Db::exec('UPDATE {users} SET last_login = ? WHERE id = ?', [now(), (int) $user['id']]);
    }

    /**
     * Setzt ein neues Passwort und beendet damit alle anderen angemeldeten Sitzungen dieses Kontos.
     * Gibt die neue Sitzungs-Kennung zurück, damit die aktuelle Sitzung angemeldet bleiben kann.
     */
    public static function setPassword(int $userId, string $password): int
    {
        Db::exec(
            'UPDATE {users} SET password_hash = ?, auth_epoch = auth_epoch + 1, token_hash = NULL, token_purpose = NULL, token_expires = NULL WHERE id = ?',
            [password_hash($password, PASSWORD_DEFAULT), $userId]
        );
        return (int) Db::val('SELECT auth_epoch FROM {users} WHERE id = ?', [$userId]);
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
        self::$loaded = false;
        self::$user = null;
    }

    public static function passwordProblem(string $password, string $email = ''): ?string
    {
        if (mb_strlen($password) < 10) {
            return 'Das Passwort muss mindestens 10 Zeichen lang sein.';
        }
        if (mb_strlen($password) > 200) {
            return 'Das Passwort ist zu lang (höchstens 200 Zeichen).';
        }
        if ($email !== '' && mb_strtolower($password) === mb_strtolower($email)) {
            return 'Das Passwort darf nicht der E-Mail-Adresse entsprechen.';
        }
        return null;
    }

    /** Erzeugt einen Einmal-Link-Token (nur der Hash wird gespeichert). */
    public static function issueToken(int $userId, string $purpose, int $hours): string
    {
        $token = bin2hex(random_bytes(32));
        Db::exec(
            'UPDATE {users} SET token_hash = ?, token_purpose = ?, token_expires = ? WHERE id = ?',
            [hash('sha256', $token), $purpose, date('Y-m-d H:i:s', time() + $hours * 3600), $userId]
        );
        return $token;
    }

    public static function userByToken(string $token): ?array
    {
        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            return null;
        }
        $u = Db::one(
            'SELECT id, name, email, rolle, aktiv, password_hash, token_expires FROM {users} WHERE token_hash = ?',
            [hash('sha256', $token)]
        );
        if ($u === null || (int) $u['aktiv'] !== 1 || (string) $u['token_expires'] < now()) {
            return null;
        }
        return $u;
    }

    public static function activeAdminCount(): int
    {
        return (int) Db::val("SELECT COUNT(*) FROM {users} WHERE rolle = 'admin' AND aktiv = 1");
    }
}
