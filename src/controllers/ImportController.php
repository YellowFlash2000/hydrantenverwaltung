<?php
declare(strict_types=1);

/** Import und Export von Hydranten (CSV) und Bildern (aus dem Importordner). Nur für Admins. */
final class ImportController extends Controller
{
    private const DEFAULT_MODES = ['update', 'new', 'ignore', 'ask'];

    public function index(): void
    {
        $this->cleanup();
        $this->render('admin/import', [
            'title'    => 'Import und Export',
            'files'    => count(ImageImport::files()),
            'writable' => ImageImport::dirWritable(),
            'hydrants' => (int) Db::val('SELECT COUNT(*) FROM {hydrants}'),
            'scripts'  => ['js/import.js'],
        ]);
    }

    // ---- Muster und Export ----------------------------------------------------------

    public function sampleHydrants(): void
    {
        $this->download('hydranten-muster.csv', HydrantImport::sample());
    }

    public function sampleImages(): void
    {
        $this->download('bilder-muster.csv', ImageImport::sample());
    }

    public function export(): void
    {
        Audit::log('hydranten_exportiert', 'hydrant', null, 'CSV-Export');
        $this->download('hydranten-' . date('Y-m-d') . '.csv', HydrantImport::export());
    }

    private function download(string $name, string $content): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . strlen($content));
        echo $content;
    }

    // ---- Hydranten ------------------------------------------------------------------

    public function hydrantsUpload(): void
    {
        $default = $this->post('default', 'ask');
        if (!in_array($default, self::DEFAULT_MODES, true)) {
            $default = 'ask';
        }
        [$text, $err] = $this->readUpload('datei');
        if ($text === null) {
            flash('error', (string) $err);
            redirect('admin/import');
        }
        $token = bin2hex(random_bytes(16));
        if (@file_put_contents($this->tmp('csv', $token), $text) === false) {
            flash('error', 'Die Datei konnte nicht zwischengespeichert werden (Schreibrechte für storage/tmp prüfen).');
            redirect('admin/import');
        }
        $plan = HydrantImport::plan($text, $default);
        if ($plan['fatal'] !== null) {
            @unlink($this->tmp('csv', $token));
            flash('error', $plan['fatal']);
            redirect('admin/import');
        }
        $this->renderPlan($plan, $token, $default, []);
    }

    public function hydrantsRun(): void
    {
        $token = $this->post('token');
        $path = $this->tmpPath('csv', $token);
        $text = $path !== null ? (string) @file_get_contents($path) : '';
        if ($text === '') {
            flash('error', 'Die hochgeladene Datei ist nicht mehr vorhanden. Bitte die CSV-Datei erneut hochladen.');
            redirect('admin/import');
        }
        $default = $this->post('default', 'ask');
        if (!in_array($default, self::DEFAULT_MODES, true)) {
            $default = 'ask';
        }
        $decisions = [];
        $modes = is_array($_POST['mode'] ?? null) ? $_POST['mode'] : [];
        $newK = is_array($_POST['newkennung'] ?? null) ? $_POST['newkennung'] : [];
        foreach ($modes as $line => $m) {
            if (is_string($m)) {
                $decisions[(int) $line] = ['mode' => $m, 'newkennung' => is_string($newK[$line] ?? null) ? $newK[$line] : ''];
            }
        }
        $plan = HydrantImport::plan($text, $default, $decisions);
        if ($plan['fatal'] !== null) {
            flash('error', $plan['fatal']);
            redirect('admin/import');
        }
        if (($this->post('aktion') !== 'ausfuehren') || $plan['undecided'] > 0 || $plan['problems'] > 0) {
            $check = $this->post('aktion') === 'ausfuehren';
            $this->renderPlan($plan, $token, $default, $decisions, $check);
            return;
        }
        $done = HydrantImport::apply($plan);
        @unlink($path);
        $skipped = (int) ($plan['counts']['error'] ?? 0);
        Audit::log('hydranten_importiert', 'hydrant', null, $done['new'] . ' neu, ' . $done['update'] . ' aktualisiert, ' . $skipped . ' Fehlerzeilen übersprungen, ' . (int) ($plan['counts']['ignore'] ?? 0) . ' ignoriert');
        flash('success', 'Import abgeschlossen: ' . $done['new'] . ' neu angelegt, ' . $done['update'] . ' aktualisiert'
            . ((int) ($plan['counts']['unchanged'] ?? 0) > 0 ? ', ' . $plan['counts']['unchanged'] . ' unverändert' : '')
            . ((int) ($plan['counts']['ignore'] ?? 0) > 0 ? ', ' . $plan['counts']['ignore'] . ' ignoriert' : '')
            . ($skipped > 0 ? ', ' . $skipped . ' fehlerhafte Zeilen übersprungen' : '') . '.');
        redirect('hydranten');
    }

    private function renderPlan(array $plan, string $token, string $default, array $decisions, bool $blocked = false): void
    {
        $this->render('admin/import_hydrants', [
            'title'   => 'Import prüfen',
            'plan'    => $plan,
            'token'   => $token,
            'default' => $default,
            'blocked' => $blocked,
            'scripts' => ['js/import.js'],
        ]);
    }

    // ---- Bilder ---------------------------------------------------------------------

    public function imagesUpload(): void
    {
        [$text, $err] = $this->readUpload('datei');
        if ($text === null) {
            flash('error', (string) $err);
            redirect('admin/import');
        }
        $parsed = ImageImport::parse($text);
        if ($parsed['fatal'] !== null) {
            flash('error', (string) $parsed['fatal']);
            redirect('admin/import');
        }
        $token = bin2hex(random_bytes(16));
        $job = ['rows' => $parsed['rows'], 'cursor' => 0, 'results' => []];
        if (!$this->saveJob($token, $job)) {
            flash('error', 'Der Auftrag konnte nicht zwischengespeichert werden (Schreibrechte für storage/tmp prüfen).');
            redirect('admin/import');
        }
        $files = ImageImport::files();
        $pending = [];
        $checks = [];
        foreach ($parsed['rows'] as $r) {
            $c = ImageImport::check($r, $files, $pending);
            if ($c['error'] === null && $c['hydrant'] !== null) {
                $pending[(int) $c['hydrant']['id']] = ($pending[(int) $c['hydrant']['id']] ?? 0) + 1;
            }
            $checks[] = ['row' => $r, 'error' => $c['error']];
        }
        $used = [];
        foreach ($parsed['rows'] as $r) {
            $used[mb_strtolower((string) $r['datei'])] = true;
        }
        $unused = array_values(array_filter($files, static fn ($lower, $name) => !isset($used[$name]), ARRAY_FILTER_USE_BOTH));
        $this->render('admin/import_images', [
            'title'    => 'Bilder-Import prüfen',
            'mode'     => 'preview',
            'token'    => $token,
            'checks'   => $checks,
            'unused'   => $unused,
            'writable' => ImageImport::dirWritable(),
            'scripts'  => ['js/import.js'],
        ]);
    }

    public function imagesRun(): void
    {
        $token = $this->post('token');
        $job = $this->loadJob($token);
        if ($job === null) {
            flash('error', 'Der Auftrag ist nicht mehr vorhanden. Bitte die Zuordnungs-CSV erneut hochladen.');
            redirect('admin/import');
        }
        $limit = (int) ini_get('max_execution_time');
        $budget = $limit > 0 ? max(5.0, min(20.0, $limit * 0.5)) : 20.0;
        @set_time_limit($limit > 0 ? max($limit, 60) : 0);
        $t0 = microtime(true);
        $total = count($job['rows']);
        while ($job['cursor'] < $total && (microtime(true) - $t0) < $budget) {
            $row = $job['rows'][$job['cursor']];
            [$ok, $msg] = ImageImport::process($row);
            $job['results'][] = ['line' => $row['line'], 'kennung' => $row['kennung'], 'datei' => $row['datei'], 'ok' => $ok, 'msg' => $msg];
            $job['cursor']++;
        }
        $this->saveJob($token, $job);
        $finished = $job['cursor'] >= $total;
        if ($finished) {
            $ok = count(array_filter($job['results'], static fn ($r) => $r['ok']));
            Audit::log('bilder_importiert', 'hydrant', null, $ok . ' von ' . $total . ' Bildern importiert');
            @unlink($this->tmp('job', $token));
        }
        $this->render('admin/import_images', [
            'title'    => $finished ? 'Bilder-Import abgeschlossen' : 'Bilder werden importiert …',
            'mode'     => $finished ? 'done' : 'progress',
            'token'    => $token,
            'results'  => $job['results'],
            'total'    => $total,
            'writable' => ImageImport::dirWritable(),
            'scripts'  => ['js/import.js'],
        ]);
    }

    // ---- Hilfen ---------------------------------------------------------------------

    /** @return array{0: ?string, 1: ?string} [Text als UTF-8, Fehlermeldung] */
    private function readUpload(string $field): array
    {
        $f = $_FILES[$field] ?? null;
        if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return [null, 'Bitte eine CSV-Datei auswählen.'];
        }
        if ($f['error'] !== UPLOAD_ERR_OK) {
            return [null, 'Die Datei konnte nicht hochgeladen werden (Fehlercode ' . (int) $f['error'] . '). Eventuell ist sie größer, als der Server erlaubt.'];
        }
        if ((int) $f['size'] > Csv::MAX_BYTES) {
            return [null, 'Die Datei ist zu groß (höchstens 5 MB).'];
        }
        if (!in_array(strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION)), ['csv', 'txt'], true)) {
            return [null, 'Bitte eine Datei mit der Endung .csv hochladen.'];
        }
        $raw = (string) @file_get_contents((string) $f['tmp_name']);
        if ($raw === '') {
            return [null, 'Die Datei ist leer.'];
        }
        return [Csv::toUtf8($raw), null];
    }

    private function tmp(string $kind, string $token): string
    {
        $dir = STORAGE . '/tmp';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir . '/import-' . $kind . '-' . $token . ($kind === 'csv' ? '.csv' : '.json');
    }

    private function tmpPath(string $kind, string $token): ?string
    {
        if (preg_match('/^[a-f0-9]{32}$/', $token) !== 1) {
            return null;
        }
        $p = $this->tmp($kind, $token);
        return is_file($p) ? $p : null;
    }

    private function saveJob(string $token, array $job): bool
    {
        return @file_put_contents($this->tmp('job', $token), json_encode($job, JSON_UNESCAPED_UNICODE)) !== false;
    }

    private function loadJob(string $token): ?array
    {
        $p = $this->tmpPath('job', $token);
        $d = $p === null ? null : json_decode((string) @file_get_contents($p), true);
        return is_array($d) && isset($d['rows'], $d['cursor'], $d['results']) ? $d : null;
    }

    /** Alte Zwischendateien (älter als 1 Tag) entfernen. */
    private function cleanup(): void
    {
        foreach (glob(STORAGE . '/tmp/import-*') ?: [] as $f) {
            if (is_file($f) && filemtime($f) < time() - 86400) {
                @unlink($f);
            }
        }
    }
}
