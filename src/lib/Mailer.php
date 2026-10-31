<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

final class Mailer
{
    /** Sendet eine Text-Mail. Rueckgabe: null bei Erfolg, sonst die Fehlermeldung. */
    public static function send(string $to, string $subject, string $body): ?string
    {
        require_once SRC . '/vendor/PHPMailer/Exception.php';
        require_once SRC . '/vendor/PHPMailer/PHPMailer.php';
        require_once SRC . '/vendor/PHPMailer/SMTP.php';

        try {
            $m = new PHPMailer(true);
            $m->CharSet = 'UTF-8';
            $m->Timeout = 12;

            $host = trim(Settings::get('mail_host'));
            if ($host !== '') {
                $m->isSMTP();
                $m->Host = $host;
                $m->Port = (int) (Settings::get('mail_port') ?: 587);
                $user = Settings::get('mail_user');
                $m->SMTPAuth = $user !== '';
                if ($user !== '') {
                    $m->Username = $user;
                    $m->Password = Crypto::decrypt(Settings::get('mail_pass_enc'));
                }
                $secure = Settings::get('mail_secure');
                if ($secure === 'ssl') {
                    $m->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                } elseif ($secure === 'tls') {
                    $m->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                } else {
                    $m->SMTPSecure = '';
                    $m->SMTPAutoTLS = false;
                }
            } else {
                $m->isMail();
            }

            $from = trim(Settings::get('mail_from'));
            if ($from === '') {
                $domain = (string) parse_url((string) Config::get('base_url', ''), PHP_URL_HOST);
                $domain = preg_replace('/^www\./', '', $domain) ?: 'localhost';
                $from = 'noreply@' . $domain;
            }
            $fromName = trim(Settings::get('mail_from_name')) ?: Settings::get('app_name');
            $m->setFrom($from, $fromName);
            $m->addAddress($to);
            $m->Subject = $subject;
            $m->isHTML(false);
            $m->Body = $body;
            $m->send();
            return null;
        } catch (\Throwable $e) {
            error_log('Hydrantenverwaltung: Mailversand fehlgeschlagen: ' . $e->getMessage());
            return $e->getMessage();
        }
    }
}
