<?php
declare(strict_types=1);

/** Begrenzung von Fehlversuchen (Login) und Anfragen (Passwort vergessen). */
final class Throttle
{
    private static function cutoff(int $windowSec): string
    {
        return date('Y-m-d H:i:s', time() - $windowSec);
    }

    public static function tooMany(string $kind, string $email, string $ip, int $maxPerEmail, int $maxPerIp, int $windowSec): bool
    {
        $cut = self::cutoff($windowSec);
        $byEmail = (int) Db::val(
            'SELECT COUNT(*) FROM {login_attempts} WHERE kind = ? AND email = ? AND attempted_at >= ?',
            [$kind, $email, $cut]
        );
        if ($byEmail >= $maxPerEmail) {
            return true;
        }
        $byIp = (int) Db::val(
            'SELECT COUNT(*) FROM {login_attempts} WHERE kind = ? AND ip = ? AND attempted_at >= ?',
            [$kind, $ip, $cut]
        );
        return $byIp >= $maxPerIp;
    }

    public static function hit(string $kind, string $email, string $ip): void
    {
        Db::exec(
            'INSERT INTO {login_attempts} (kind, email, ip, attempted_at) VALUES (?, ?, ?, ?)',
            [$kind, mb_substr($email, 0, 190), $ip, now()]
        );
    }

    public static function clear(string $kind, string $email): void
    {
        Db::exec('DELETE FROM {login_attempts} WHERE kind = ? AND email = ?', [$kind, mb_substr($email, 0, 190)]);
    }

    /** Alte Eintraege gelegentlich entfernen. */
    public static function purge(): void
    {
        if (random_int(1, 20) === 1) {
            Db::exec('DELETE FROM {login_attempts} WHERE attempted_at < ?', [self::cutoff(86400)]);
        }
    }
}
