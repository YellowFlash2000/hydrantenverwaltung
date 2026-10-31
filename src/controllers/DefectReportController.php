<?php
declare(strict_types=1);

/** Mängelübersicht über einen oder mehrere Prüfdurchgänge, mit CSV-Export (ab Rolle Bearbeiter). */
final class DefectReportController extends Controller
{
    /** @return array{0:int[],1:string,2:bool} */
    private function filters(): array
    {
        $all = Campaign::all();
        $valid = array_map(static fn ($c) => (int) $c['id'], $all);
        $valid[] = DefectReport::NO_CAMPAIGN;
        if (isset($_GET['pruefung']) && is_array($_GET['pruefung'])) {
            $ids = array_values(array_intersect($valid, array_map('intval', $_GET['pruefung'])));
        } elseif (isset($_GET['filter'])) {
            $ids = [];
        } else {
            $a = Campaign::active() ?? ($all[0] ?? null);
            $ids = $a === null ? [DefectReport::NO_CAMPAIGN] : [(int) $a['id'], DefectReport::NO_CAMPAIGN];
        }
        $tour = isset($_GET['tour']) && is_string($_GET['tour']) ? trim($_GET['tour']) : '';
        $withOk = ($_GET['alle'] ?? '') === '1';
        return [$ids, $tour, $withOk];
    }

    public function index(): void
    {
        [$ids, $tour, $withOk] = $this->filters();
        $this->render('defects/report', [
            'title' => 'Mängelübersicht', 'wide' => true, 'campaigns' => Campaign::all(), 'selected' => $ids, 'tour' => $tour, 'withOk' => $withOk,
            'tourNames' => DefectReport::tourNames(), 'rows' => DefectReport::rows($ids, $tour, $withOk),
        ]);
    }

    public function export(): void
    {
        [$ids, $tour, $withOk] = $this->filters();
        $rows = DefectReport::rows($ids, $tour, $withOk);
        Audit::log('maengel_exportiert', 'revision', null, count($rows) . ' Zeilen');
        $content = DefectReport::csv($rows);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="maengel-' . date('Y-m-d') . '.csv"');
        header('Content-Length: ' . strlen($content));
        echo $content;
    }
}
