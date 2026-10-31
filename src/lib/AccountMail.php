<?php
declare(strict_types=1);

/** Texte der Konto-Mails (Einladung, Passwort zuruecksetzen). */
final class AccountMail
{
    /**
     * @param string $purpose 'invite' oder 'reset'
     * @return array{0: ?string, 1: string} [Fehlermeldung oder null, Link]
     */
    public static function sendLink(array $user, string $token, string $purpose, int $hours): array
    {
        $link = abs_url('passwort/setzen/' . $token);
        $app = Settings::get('app_name');
        $until = date('d.m.Y H:i', time() + $hours * 3600);

        if ($purpose === 'invite') {
            $subject = 'Ihr Zugang zu ' . $app;
            $intro = "für Sie wurde ein Zugang zu \"{$app}\" angelegt.\nÜber den folgenden Link legen Sie Ihr Passwort fest:";
        } else {
            $subject = 'Passwort zurücksetzen – ' . $app;
            $intro = "für Ihr Konto bei \"{$app}\" wurde das Zurücksetzen des Passworts angefordert.\nÜber den folgenden Link legen Sie ein neues Passwort fest:";
        }

        $body = "Hallo {$user['name']},\n\n{$intro}\n\n{$link}\n\n"
            . "Der Link ist bis {$until} Uhr gültig und kann nur einmal verwendet werden.\n"
            . "Falls Sie diese Nachricht nicht erwartet haben, können Sie sie ignorieren.\n";

        return [Mailer::send((string) $user['email'], $subject, $body), $link];
    }
}
