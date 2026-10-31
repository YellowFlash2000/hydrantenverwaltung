<?php
declare(strict_types=1);

final class AuthController extends Controller
{
    private const MSG_LOGIN_FAILED = 'E-Mail-Adresse oder Passwort ist falsch.';

    public function loginForm(): void
    {
        if (Auth::check()) {
            redirect('');
        }
        $this->render('auth/login', ['title' => 'Anmelden', 'email' => '', 'error' => null]);
    }

    public function login(): void
    {
        $email = mb_strtolower($this->post('email'));
        $password = (string) ($_POST['password'] ?? '');
        $ip = client_ip();

        Throttle::purge();
        if (Throttle::tooMany('login', $email, $ip, 5, 20, 900)) {
            $this->render('auth/login', [
                'title' => 'Anmelden',
                'email' => $email,
                'error' => 'Zu viele Fehlversuche. Bitte warten Sie 15 Minuten und versuchen Sie es dann erneut.',
            ], 429);
            return;
        }

        $user = $email !== '' ? Db::one('SELECT * FROM {users} WHERE email = ?', [$email]) : null;
        // Auch bei unbekanntem Konto Rechenzeit aufwenden, damit sich Konten nicht per Antwortzeit erraten lassen.
        $hash = $user['password_hash'] ?? password_hash('dummy-password', PASSWORD_DEFAULT);
        $ok = password_verify($password, (string) $hash);

        if ($user === null || !$ok || (int) $user['aktiv'] !== 1 || $user['password_hash'] === null) {
            Throttle::hit('login', $email, $ip);
            Audit::log('login_fehlgeschlagen', 'user', $user['id'] ?? null, '', $email);
            $this->render('auth/login', ['title' => 'Anmelden', 'email' => $email, 'error' => self::MSG_LOGIN_FAILED], 401);
            return;
        }

        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            Db::exec('UPDATE {users} SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), (int) $user['id']]);
        }
        $next = safe_next($_SESSION['next'] ?? null);
        Auth::login($user);
        unset($_SESSION['next']);
        Throttle::clear('login', $email);
        Audit::log('login', 'user', (int) $user['id']);
        AuditLog::cleanup();
        redirect($next ?? '');
    }

    public function logout(): void
    {
        Audit::log('logout', 'user', Auth::user()['id'] ?? null);
        Auth::logout();
        flash('success', 'Sie wurden abgemeldet.');
        redirect('login');
    }

    public function forgotForm(): void
    {
        $this->render('auth/forgot', ['title' => 'Passwort vergessen']);
    }

    public function forgot(): void
    {
        $email = mb_strtolower($this->post('email'));
        $ip = client_ip();
        $generic = 'Wenn zu dieser Adresse ein Konto existiert, wurde eine E-Mail mit einem Link zum Zurücksetzen versendet.';

        Throttle::purge();
        if (!Throttle::tooMany('reset', $email, $ip, 3, 10, 3600)) {
            Throttle::hit('reset', $email, $ip);
            $user = $email !== '' ? Db::one('SELECT id, name, email, aktiv FROM {users} WHERE email = ?', [$email]) : null;
            if ($user !== null && (int) $user['aktiv'] === 1) {
                $token = Auth::issueToken((int) $user['id'], 'reset', 2);
                [$err] = AccountMail::sendLink($user, $token, 'reset', 2);
                Audit::log('passwort_reset_angefordert', 'user', (int) $user['id'], $err === null ? '' : 'Mailversand fehlgeschlagen', $email);
            }
        }
        flash('info', $generic);
        redirect('login');
    }

    public function setPasswordForm(array $params): void
    {
        $user = Auth::userByToken($params['token'] ?? '');
        if ($user === null) {
            $this->invalidLink();
            return;
        }
        $this->render('auth/set_password', ['title' => 'Passwort festlegen', 'user' => $user, 'token' => $params['token'], 'error' => null]);
    }

    public function setPassword(array $params): void
    {
        $user = Auth::userByToken($params['token'] ?? '');
        if ($user === null) {
            $this->invalidLink();
            return;
        }
        $pass = (string) ($_POST['password'] ?? '');
        $error = Auth::passwordProblem($pass, (string) $user['email']);
        if ($error === null && $pass !== (string) ($_POST['password2'] ?? '')) {
            $error = 'Die beiden Passwörter stimmen nicht überein.';
        }
        if ($error !== null) {
            $this->render('auth/set_password', ['title' => 'Passwort festlegen', 'user' => $user, 'token' => $params['token'], 'error' => $error], 422);
            return;
        }
        Auth::setPassword((int) $user['id'], $pass);
        Throttle::clear('login', (string) $user['email']);
        Audit::log('passwort_gesetzt', 'user', (int) $user['id'], '', (string) $user['email']);
        flash('success', 'Ihr Passwort wurde gespeichert. Sie können sich jetzt anmelden.');
        redirect('login');
    }

    public function changePasswordForm(): void
    {
        $this->render('auth/change_password', ['title' => 'Passwort ändern', 'error' => null]);
    }

    public function changePassword(): void
    {
        $me = Auth::user();
        $row = Db::one('SELECT password_hash FROM {users} WHERE id = ?', [(int) $me['id']]);
        $current = (string) ($_POST['current'] ?? '');
        $new = (string) ($_POST['password'] ?? '');

        $error = null;
        if ($row === null || !password_verify($current, (string) $row['password_hash'])) {
            $error = 'Das aktuelle Passwort ist falsch.';
        } elseif (($error = Auth::passwordProblem($new, (string) $me['email'])) === null && $new !== (string) ($_POST['password2'] ?? '')) {
            $error = 'Die beiden neuen Passwörter stimmen nicht überein.';
        }
        if ($error !== null) {
            $this->render('auth/change_password', ['title' => 'Passwort ändern', 'error' => $error], 422);
            return;
        }
        $epoch = Auth::setPassword((int) $me['id'], $new);
        session_regenerate_id(true);
        $_SESSION['epoch'] = $epoch; // diese Sitzung bleibt gültig, alle anderen enden
        Audit::log('passwort_geaendert', 'user', (int) $me['id']);
        flash('success', 'Ihr Passwort wurde geändert.');
        redirect('');
    }

    private function invalidLink(): void
    {
        $this->fail(410, 'Link ungültig', 'Dieser Link ist ungültig oder abgelaufen. Fordern Sie über „Passwort vergessen“ einen neuen Link an oder bitten Sie den Administrator um eine neue Einladung.');
    }
}
