<?php
declare(strict_types=1);

final class SettingsController extends Controller
{
    public function index(): void
    {
        $s = Settings::all();
        $s['mail_pass_set'] = $s['mail_pass_enc'] !== '' ? '1' : '0';
        unset($s['mail_pass_enc']);
        $this->render('admin/settings', ['title' => 'Einstellungen', 's' => $s, 'errors' => [], 'open' => 'design'] + $this->mapAssets());
    }

    public function save(): void
    {
        $section = $this->post('section');
        $errors = [];

        switch ($section) {
            case 'design':
                $errors = $this->saveDesign();
                break;
            case 'felder':
                $errors = $this->saveFields();
                break;
            case 'karte':
                $errors = $this->saveMap();
                break;
            case 'mail':
                $errors = $this->saveMail();
                break;
            case 'protokoll':
                $errors = $this->saveAudit();
                break;
            default:
                $this->fail(400, 'Ungültige Anfrage', 'Unbekannter Einstellungsbereich.');
                return;
        }

        if ($errors) {
            $s = Settings::all();
            foreach ($_POST as $k => $v) {
                if (is_string($v) && array_key_exists($k, $s) && $k !== 'mail_pass_enc') {
                    $s[$k] = $v;
                }
            }
            $s['mail_pass_set'] = $s['mail_pass_enc'] !== '' ? '1' : '0';
            unset($s['mail_pass_enc']);
            $this->render('admin/settings', ['title' => 'Einstellungen', 's' => $s, 'errors' => $errors, 'open' => $section] + $this->mapAssets(), 422);
            return;
        }
        Audit::log('einstellungen_geaendert', 'settings', null, $section);
        flash('success', 'Die Einstellungen wurden gespeichert.');
        redirect('admin/einstellungen');
    }

    /** @return array<string, string[]> */
    private function mapAssets(): array
    {
        return ['styles' => ['vendor/leaflet/leaflet.css'], 'scripts' => ['vendor/leaflet/leaflet.js', 'js/map.js']];
    }

    public function testMail(): void
    {
        $me = Auth::user();
        $err = Mailer::send(
            (string) $me['email'],
            'Testnachricht – ' . Settings::get('app_name'),
            "Dies ist eine Testnachricht der Hydrantenverwaltung.\nWenn Sie diese E-Mail lesen, funktioniert der Mailversand."
        );
        if ($err === null) {
            flash('success', 'Die Testmail wurde an ' . $me['email'] . ' versendet. Bitte auch den Spam-Ordner prüfen.');
        } else {
            flash('error', 'Der Mailversand ist fehlgeschlagen: ' . $err);
        }
        redirect('admin/einstellungen');
    }

    // ---------------------------------------------------------------------

    /** @return string[] */
    private function saveDesign(): array
    {
        $errors = [];
        $name = $this->post('app_name');
        if ($name === '' || mb_strlen($name) > 60) {
            $errors[] = 'Bitte einen Namen angeben (höchstens 60 Zeichen).';
        }
        $colors = [];
        foreach (['color_primary' => 'Hauptfarbe', 'color_header' => 'Farbe der Kopfzeile', 'color_bg' => 'Seitenhintergrund'] as $k => $label) {
            $c = strtolower($this->post($k));
            if (!Theme::validColor($c)) {
                $errors[] = "{$label}: Bitte einen Farbwert im Format #rrggbb angeben.";
            }
            $colors[$k] = $c;
        }

        // Logo
        $newLogo = null;
        $file = $_FILES['logo'] ?? null;
        if (is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Das Logo konnte nicht hochgeladen werden (Fehlercode ' . (int) $file['error'] . ').';
            } elseif ($file['size'] > 3 * 1024 * 1024) {
                $errors[] = 'Das Logo ist zu groß (höchstens 3 MB).';
            } elseif (!is_uploaded_file($file['tmp_name'])) {
                $errors[] = 'Ungültiger Upload.';
            } else {
                [$img, $err] = Images::load($file['tmp_name']);
                if ($img === null) {
                    $errors[] = 'Logo: ' . $err;
                } else {
                    $img = Images::fit($img, 600, 200);
                    $dir = STORAGE . '/branding';
                    if (!is_dir($dir)) {
                        @mkdir($dir, 0755, true);
                    }
                    $newLogo = 'logo-' . bin2hex(random_bytes(6)) . '.png';
                    if (!Images::savePng($img, $dir . '/' . $newLogo)) {
                        $errors[] = 'Das Logo konnte nicht gespeichert werden (Schreibrechte für storage/ prüfen).';
                        $newLogo = null;
                    }
                }
            }
        }
        if ($errors) {
            if ($newLogo !== null) {
                @unlink(STORAGE . '/branding/' . $newLogo);
            }
            return $errors;
        }

        $old = Settings::get('logo_file');
        $values = ['app_name' => $name] + $colors;
        if ($newLogo !== null) {
            $values['logo_file'] = $newLogo;
        } elseif (isset($_POST['logo_remove'])) {
            $values['logo_file'] = '';
        }
        Settings::setMany($values);
        Settings::bumpTheme();
        if (isset($values['logo_file']) && $old !== '' && $old !== $values['logo_file']) {
            @unlink(STORAGE . '/branding/' . basename($old));
        }
        return [];
    }

    /** @return string[] */
    private function saveFields(): array
    {
        Settings::setMany([
            'show_pressure' => isset($_POST['show_pressure']) ? '1' : '0',
            'show_flow'     => isset($_POST['show_flow']) ? '1' : '0',
            'show_position' => isset($_POST['show_position']) ? '1' : '0',
        ]);
        return [];
    }

    /** @return string[] */
    private function saveAudit(): array
    {
        $m = $this->post('audit_retention_months');
        if (!ctype_digit($m) || !isset(AuditLog::RETENTION[(int) $m])) {
            return ['Bitte eine Aufbewahrungsdauer aus der Liste wählen.'];
        }
        Settings::setMany(['audit_retention_months' => (string) (int) $m, 'audit_cleanup_last' => '']);
        AuditLog::cleanup();
        return [];
    }

    /** @return string[] */
    private function saveMap(): array
    {
        $latIn = $this->post('map_lat');
        $lonIn = $this->post('map_lon');
        $zoomIn = $this->post('map_zoom', '16');
        $errors = [];
        $lat = $lon = null;
        if ($latIn !== '' || $lonIn !== '') {
            $lat = Hydrant::parseDecimal($latIn);
            $lon = Hydrant::parseDecimal($lonIn);
            if ($latIn === '' || $lonIn === '') {
                $errors[] = 'Kartenmitte: Bitte Breite und Länge angeben oder beide Felder leer lassen.';
            } elseif ($lat === null || $lon === null || $lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
                $errors[] = 'Kartenmitte: Bitte Breite (-90 bis 90) und Länge (-180 bis 180) als Dezimalzahl angeben, z. B. 51,1234.';
            }
        }
        if (preg_match('/^\d{1,2}$/', $zoomIn) !== 1 || (int) $zoomIn < 5 || (int) $zoomIn > 19) {
            $errors[] = 'Zoomstufe: Bitte eine ganze Zahl von 5 (weit) bis 19 (sehr nah) angeben.';
        }
        if ($errors) {
            return $errors;
        }
        Settings::setMany([
            'map_lat'  => $lat === null ? '' : Hydrant::coord($lat),
            'map_lon'  => $lon === null ? '' : Hydrant::coord($lon),
            'map_zoom' => (string) (int) $zoomIn,
        ]);
        return [];
    }

    /** @return string[] */
    private function saveMail(): array
    {
        $errors = [];
        $host = $this->post('mail_host');
        $port = $this->post('mail_port', '587');
        $secure = $this->post('mail_secure', 'tls');
        $from = $this->post('mail_from');

        if ($port === '' || preg_match('/^\d{1,5}$/', $port) !== 1 || (int) $port < 1 || (int) $port > 65535) {
            $errors[] = 'Bitte einen gültigen Port angeben (1 bis 65535).';
        }
        if (!in_array($secure, ['tls', 'ssl', 'none'], true)) {
            $errors[] = 'Bitte eine gültige Verschlüsselung wählen.';
        }
        if ($from !== '' && filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Bitte eine gültige Absenderadresse angeben (oder das Feld leer lassen).';
        }
        if ($errors) {
            return $errors;
        }

        $values = [
            'mail_host'      => $host,
            'mail_port'      => (string) (int) $port,
            'mail_secure'    => $secure,
            'mail_user'      => $this->post('mail_user'),
            'mail_from'      => $from,
            'mail_from_name' => $this->post('mail_from_name'),
        ];
        $pass = (string) ($_POST['mail_pass'] ?? '');
        if ($pass !== '') {
            $values['mail_pass_enc'] = Crypto::encrypt($pass);
        } elseif (isset($_POST['mail_pass_clear'])) {
            $values['mail_pass_enc'] = '';
        }
        Settings::setMany($values);
        return [];
    }
}
