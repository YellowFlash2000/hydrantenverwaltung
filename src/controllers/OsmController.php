<?php
declare(strict_types=1);

/**
 * Hydranten aus OpenStreetMap übernehmen (nur Admin).
 * Die Daten kommen als Datei aus Overpass Turbo; das Ergebnis geht als CSV in die vorhandene Importvorschau.
 */
final class OsmController extends Controller
{
    public function index(): void
    {
        $this->renderForm(['gemeinde' => '', 'typ' => (string) (array_key_first(HydrantType::choices()) ?? ''), 'near' => '1'] + Osm::defaultOptions());
    }

    private function renderForm(array $form, array $errors = [], int $status = 200): void
    {
        $this->render('admin/osm', [
            'title' => 'Hydranten aus OpenStreetMap', 'form' => $form, 'errors' => $errors, 'result' => null,
            'types' => HydrantType::choices(), 'scripts' => ['js/osm.js'],
        ], $status);
    }

    /** Hochgeladene Datei einlesen und die Hydranten für den Import aufbereiten. */
    public function fileUpload(): void
    {
        @set_time_limit(90);
        $form = $this->readForm();
        $errors = $this->checkOptions($form);
        if (!isset(HydrantType::choices()[$form['typ']])) {
            $errors[] = 'Bitte einen Typ für Hydranten ohne Typangabe wählen.';
        }
        $f = $_FILES['datei'] ?? null;
        $nodes = [];
        if (!$errors) {
            if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $f['tmp_name'])) {
                $errors[] = 'Bitte eine Datei wählen (Fehler beim Hochladen oder Datei zu groß).';
            } elseif ((int) $f['size'] > 20 * 1024 * 1024) {
                $errors[] = 'Die Datei ist zu groß (höchstens 20 MB).';
            } else {
                try {
                    $nodes = Osm::parseNodes((string) @file_get_contents((string) $f['tmp_name']));
                } catch (RuntimeException $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }
        if ($errors) {
            $this->renderForm($form, $errors, 422);
            return;
        }
        $res = Osm::toRows($nodes, $form['typ'], $form['near'] === '1', ['k' => $form['k'], 'sep' => $form['sep'], 'bem' => $form['bem']]);
        $token = '';
        if ($res['rows']) {
            $token = bin2hex(random_bytes(16));
            if (@file_put_contents(HydrantImport::tmpFile($token), Osm::csv($res['rows'])) === false) {
                $this->renderForm($form, ['Die Daten konnten nicht zwischengespeichert werden (Schreibrechte für storage/tmp prüfen).'], 500);
                return;
            }
        }
        $area = $form['gemeinde'] !== '' ? $form['gemeinde'] : mb_substr((string) $f['name'], 0, 60);
        Audit::log('osm_abgefragt', 'hydrant', null, $area . ': ' . count($nodes) . ' Knoten, ' . count($res['rows']) . ' neu, ' . $res['near'] . ' schon vorhanden');
        $this->render('admin/osm', [
            'title' => 'Hydranten aus OpenStreetMap', 'form' => $form, 'errors' => [], 'types' => HydrantType::choices(), 'scripts' => ['js/osm.js'],
            'result' => ['area' => $area, 'found' => count($nodes), 'rows' => $res['rows'], 'near' => $res['near'], 'unknown' => $res['unknown_type'], 'token' => $token],
        ]);
    }

    /** Die zwischengespeicherte CSV herunterladen. */
    public function download(array $params): void
    {
        $token = (string) ($params['token'] ?? '');
        $path = preg_match('/^[a-f0-9]{32}$/', $token) === 1 ? HydrantImport::tmpFile($token) : '';
        if ($path === '' || !is_file($path)) {
            $this->fail(404, 'Nicht gefunden', 'Diese Datei gibt es nicht mehr. Bitte die Datei erneut hochladen.');
            return;
        }
        $content = (string) file_get_contents($path);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="osm-hydranten-' . date('Y-m-d') . '.csv"');
        header('Content-Length: ' . strlen($content));
        echo $content;
    }

    /** @return array{gemeinde:string, typ:string, near:string, k:string[], sep:string, bem:string[]} */
    private function readForm(): array
    {
        $k = [];
        foreach ([1, 2, 3] as $i) {
            $v = $this->post('k' . $i);
            $k[] = isset(Osm::KENNUNG_FIELDS[$v]) ? $v : '';
        }
        $bem = is_array($_POST['bem'] ?? null) ? array_values(array_filter($_POST['bem'], static fn ($b) => is_string($b) && isset(Osm::NOTE_FIELDS[$b]))) : [];
        $sep = $this->post('sep', 'strich');
        return [
            'gemeinde' => mb_substr($this->post('gemeinde'), 0, 100), 'typ' => $this->post('typ'), 'near' => isset($_POST['near']) ? '1' : '',
            'k' => $k, 'sep' => isset(Osm::SEPARATORS[$sep]) ? $sep : 'strich', 'bem' => $bem,
        ];
    }

    /** @return string[] Fehler in der Auswahl der Kennung */
    private function checkOptions(array $form): array
    {
        $chosen = array_values(array_filter($form['k'], static fn ($x) => $x !== ''));
        if (!$chosen) {
            return ['Bitte mindestens ein Feld für die Kennung wählen.'];
        }
        if (count($chosen) !== count(array_unique($chosen))) {
            return ['Ein Feld kann nur einmal für die Kennung gewählt werden.'];
        }
        return [];
    }
}
