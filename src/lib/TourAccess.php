<?php
declare(strict_types=1);

/** Zugang über den Tour-Link ohne Anmeldung: Teilnehmer je Tour und Gerät (Cookie) und Statusfarben. */
final class TourAccess
{
    public const COLORS = ['offen' => '#4a5f78', Revision::OK => '#2e7d32', Revision::DEFECT => '#e07b00'];

    private static function cookieName(array $tour): string
    {
        return 'tp_' . substr(hash('sha256', (string) $tour['token']), 0, 16);
    }

    private static function sign(int $outingId, array $tour): string
    {
        return hash_hmac('sha256', $outingId . '|' . $tour['token'], (string) Config::get('app_key', ''));
    }

    /** Aktuelle Teilnehmer-Eintragung dieses Geräts oder null. */
    public static function outing(array $tour): ?array
    {
        $raw = $_COOKIE[self::cookieName($tour)] ?? '';
        if (!is_string($raw) || !preg_match('/^(\d{1,12})\.([a-f0-9]{64})$/', $raw, $m)) {
            return null;
        }
        $id = (int) $m[1];
        if (!hash_equals(self::sign($id, $tour), $m[2])) {
            return null;
        }
        $o = Db::one('SELECT * FROM {tour_outings} WHERE id = ? AND tour_id = ?', [$id, (int) $tour['id']]);
        if ($o === null || (int) ($o['campaign_id'] ?? 0) !== (int) $tour['campaign_id']) {
            return null;
        }
        return $o;
    }

    /** Legt die Teilnehmer fest und merkt sie sich im Gerät. */
    public static function start(array $tour, string $names): int
    {
        $id = Db::insert(
            'INSERT INTO {tour_outings} (tour_id, campaign_id, teilnehmer, started_at) VALUES (?, ?, ?, ?)',
            [(int) $tour['id'], (int) $tour['campaign_id'], $names, now()]
        );
        setcookie(self::cookieName($tour), $id . '.' . self::sign($id, $tour), [
            'expires'  => time() + 180 * 86400,
            'path'     => base_path() . '/t/' . $tour['token'],
            'secure'   => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        return $id;
    }

    /** Name(n) bereinigen: Leerzeichen, Steuerzeichen, Länge. @return array{0:string,1:?string} */
    public static function cleanNames(string $in): array
    {
        $n = trim((string) preg_replace('/[\x00-\x1F\x7F]+|\s{2,}/u', ' ', $in));
        if ($n === '') {
            return ['', 'Bitte mindestens einen Namen eintragen.'];
        }
        if (mb_strlen($n) > 200) {
            return [$n, 'Bitte höchstens 200 Zeichen eintragen.'];
        }
        return [$n, null];
    }

    public static function statusOf(?string $result): string
    {
        return $result === Revision::DEFECT ? 'Mangel' : ($result === Revision::OK ? 'in Ordnung' : 'noch offen');
    }

    public static function colorOf(?string $result): string
    {
        return self::COLORS[$result ?? 'offen'] ?? self::COLORS['offen'];
    }

    /** Kartenpunkt eines Hydranten innerhalb des Tour-Links. */
    public static function point(array $tour, array $h): array
    {
        $p = Hydrant::mapPoint($h);
        $p['url'] = url('t/' . $tour['token'] . '/h/' . (int) $h['id']);
        $p['linkText'] = 'Zur Prüfung';
        $p['status'] = self::statusOf($h['rev_ergebnis'] ?? null);
        $p['color'] = self::colorOf($h['rev_ergebnis'] ?? null);
        return $p;
    }
}
