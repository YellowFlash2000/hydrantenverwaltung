<?php
declare(strict_types=1);

final class Audit
{
    public static function log(string $action, string $objectType = '', ?int $objectId = null, string $details = '', ?string $label = null): void
    {
        try {
            $user = Auth::user();
            Db::exec(
                'INSERT INTO {audit_log} (created_at, user_id, user_label, action, object_type, object_id, details) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [
                    now(),
                    $user['id'] ?? null,
                    mb_substr($label ?? ($user['email'] ?? 'System'), 0, 190),
                    mb_substr($action, 0, 60),
                    mb_substr($objectType, 0, 40),
                    $objectId,
                    $details === '' ? null : mb_substr($details, 0, 2000),
                ]
            );
        } catch (\Throwable $e) {
            error_log('Hydrantenverwaltung: Audit-Eintrag fehlgeschlagen: ' . $e->getMessage());
        }
    }
}
