<?php
declare(strict_types=1);

/** Verschluesselt einzelne Einstellungen (z. B. das SMTP-Passwort) mit dem app_key aus der config.php. */
final class Crypto
{
    private static function key(): string
    {
        return hash('sha256', (string) Config::get('app_key', ''), true);
    }

    public static function encrypt(string $plain): string
    {
        if ($plain === '') {
            return '';
        }
        $iv = random_bytes(12);
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new RuntimeException('Verschlüsselung fehlgeschlagen.');
        }
        return base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $stored): string
    {
        if ($stored === '') {
            return '';
        }
        $raw = base64_decode($stored, true);
        if ($raw === false || strlen($raw) < 29) {
            return '';
        }
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $plain === false ? '' : $plain;
    }
}
