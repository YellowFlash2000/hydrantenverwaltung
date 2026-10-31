<?php
declare(strict_types=1);

final class UserController extends Controller
{
    private const INVITE_HOURS = 168;
    private const RESET_HOURS = 2;

    public function index(): void
    {
        $users = Db::all(
            'SELECT id, name, email, rolle, aktiv, password_hash IS NULL AS pending, last_login FROM {users} ORDER BY name'
        );
        $this->render('admin/users_list', ['title' => 'Benutzer', 'users' => $users, 'me' => Auth::user()]);
    }

    public function createForm(): void
    {
        $this->render('admin/user_form', [
            'title'  => 'Benutzer anlegen',
            'target' => null,
            'form'   => ['name' => '', 'email' => '', 'rolle' => 'reviser', 'aktiv' => 1],
            'errors' => [],
            'self'   => false,
        ]);
    }

    public function create(): void
    {
        $form = $this->readForm();
        $errors = $this->validate($form, null);
        if ($errors) {
            $this->render('admin/user_form', ['title' => 'Benutzer anlegen', 'target' => null, 'form' => $form, 'errors' => $errors, 'self' => false], 422);
            return;
        }

        $id = Db::insert(
            'INSERT INTO {users} (name, email, password_hash, rolle, aktiv, created_at) VALUES (?, ?, NULL, ?, 1, ?)',
            [$form['name'], $form['email'], $form['rolle'], now()]
        );
        Audit::log('benutzer_angelegt', 'user', $id, $form['email'] . ' (' . $form['rolle'] . ')');
        $this->sendLinkAndFlash($id, 'invite', 'Der Benutzer wurde angelegt.');
        redirect('admin/benutzer');
    }

    public function editForm(array $params): void
    {
        $target = $this->find((int) ($params['id'] ?? 0));
        if ($target === null) {
            $this->fail(404, 'Nicht gefunden', 'Diesen Benutzer gibt es nicht.');
            return;
        }
        $this->render('admin/user_form', [
            'title'  => 'Benutzer bearbeiten',
            'target' => $target,
            'form'   => ['name' => $target['name'], 'email' => $target['email'], 'rolle' => $target['rolle'], 'aktiv' => (int) $target['aktiv']],
            'errors' => [],
            'self'   => (int) $target['id'] === (int) Auth::user()['id'],
        ]);
    }

    public function update(array $params): void
    {
        $target = $this->find((int) ($params['id'] ?? 0));
        if ($target === null) {
            $this->fail(404, 'Nicht gefunden', 'Diesen Benutzer gibt es nicht.');
            return;
        }
        $self = (int) $target['id'] === (int) Auth::user()['id'];
        $form = $this->readForm();
        if ($self) {
            // Das eigene Konto darf sich nicht selbst herabstufen oder sperren.
            $form['rolle'] = (string) $target['rolle'];
            $form['aktiv'] = 1;
        }
        $errors = $this->validate($form, (int) $target['id']);

        $wasActiveAdmin = $target['rolle'] === 'admin' && (int) $target['aktiv'] === 1;
        $staysActiveAdmin = $form['rolle'] === 'admin' && $form['aktiv'] === 1;
        if ($wasActiveAdmin && !$staysActiveAdmin && Auth::activeAdminCount() <= 1) {
            $errors[] = 'Es muss mindestens ein aktiver Admin vorhanden sein.';
        }

        if ($errors) {
            $this->render('admin/user_form', ['title' => 'Benutzer bearbeiten', 'target' => $target, 'form' => $form, 'errors' => $errors, 'self' => $self], 422);
            return;
        }

        Db::exec(
            'UPDATE {users} SET name = ?, email = ?, rolle = ?, aktiv = ? WHERE id = ?',
            [$form['name'], $form['email'], $form['rolle'], $form['aktiv'], (int) $target['id']]
        );
        $changes = [];
        foreach (['name' => 'Name', 'email' => 'E-Mail', 'rolle' => 'Rolle'] as $k => $label) {
            if ((string) $target[$k] !== (string) $form[$k]) {
                $changes[] = "{$label}: {$target[$k]} → {$form[$k]}";
            }
        }
        if ((int) $target['aktiv'] !== $form['aktiv']) {
            $changes[] = $form['aktiv'] === 1 ? 'aktiviert' : 'gesperrt';
        }
        Audit::log('benutzer_geaendert', 'user', (int) $target['id'], implode('; ', $changes));
        flash('success', 'Die Änderungen wurden gespeichert.');
        redirect('admin/benutzer');
    }

    /** Einladung (noch kein Passwort) oder Passwort-Reset-Link verschicken. */
    public function sendLink(array $params): void
    {
        $target = $this->find((int) ($params['id'] ?? 0));
        if ($target === null) {
            $this->fail(404, 'Nicht gefunden', 'Diesen Benutzer gibt es nicht.');
            return;
        }
        if ((int) $target['aktiv'] !== 1) {
            flash('error', 'Das Konto ist gesperrt. Bitte zuerst aktivieren.');
            redirect('admin/benutzer/' . (int) $target['id']);
        }
        $purpose = $target['password_hash'] === null ? 'invite' : 'reset';
        $this->sendLinkAndFlash((int) $target['id'], $purpose, $purpose === 'invite' ? 'Die Einladung wurde erneut erzeugt.' : 'Der Link zum Zurücksetzen wurde erzeugt.');
        redirect('admin/benutzer/' . (int) $target['id']);
    }

    // ---------------------------------------------------------------------

    private function find(int $id): ?array
    {
        return $id > 0 ? Db::one('SELECT id, name, email, rolle, aktiv, password_hash, last_login FROM {users} WHERE id = ?', [$id]) : null;
    }

    private function readForm(): array
    {
        return [
            'name'  => $this->post('name'),
            'email' => mb_strtolower($this->post('email')),
            'rolle' => $this->post('rolle'),
            'aktiv' => isset($_POST['aktiv']) ? 1 : 0,
        ];
    }

    /** @return string[] */
    private function validate(array $form, ?int $ignoreId): array
    {
        $errors = [];
        if ($form['name'] === '' || mb_strlen($form['name']) > 120) {
            $errors[] = 'Bitte einen Namen angeben (höchstens 120 Zeichen).';
        }
        if (filter_var($form['email'], FILTER_VALIDATE_EMAIL) === false || mb_strlen($form['email']) > 190) {
            $errors[] = 'Bitte eine gültige E-Mail-Adresse angeben.';
        } else {
            $dup = Db::val('SELECT COUNT(*) FROM {users} WHERE email = ? AND id <> ?', [$form['email'], $ignoreId ?? 0]);
            if ((int) $dup > 0) {
                $errors[] = 'Diese E-Mail-Adresse wird bereits verwendet.';
            }
        }
        if (!isset(Auth::ROLES[$form['rolle']])) {
            $errors[] = 'Bitte eine gültige Rolle wählen.';
        }
        return $errors;
    }

    private function sendLinkAndFlash(int $userId, string $purpose, string $okMessage): void
    {
        $user = $this->find($userId);
        $hours = $purpose === 'invite' ? self::INVITE_HOURS : self::RESET_HOURS;
        $token = Auth::issueToken($userId, $purpose, $hours);
        [$err, $link] = AccountMail::sendLink($user, $token, $purpose, $hours);

        if ($err === null) {
            flash('success', $okMessage . ' Eine E-Mail mit dem Link wurde an ' . $user['email'] . ' gesendet.');
        } else {
            $until = date('d.m.Y H:i', time() + $hours * 3600);
            flash('warning', $okMessage . ' Die E-Mail konnte aber nicht versendet werden (Mail-Einstellungen prüfen). '
                . 'Sie können diesen Link selbst weitergeben (gültig bis ' . $until . ' Uhr): ' . $link);
        }
        Audit::log($purpose === 'invite' ? 'einladung_erzeugt' : 'reset_link_erzeugt', 'user', $userId, $err === null ? 'per E-Mail' : 'Mailversand fehlgeschlagen');
    }
}
