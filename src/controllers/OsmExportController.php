<?php
declare(strict_types=1);

/** Export der (gefilterten) Hydrantenliste für OpenStreetMap. Nur Admin. */
final class OsmExportController extends Controller
{
    /** Optionsseite; der Filter der Hydrantenliste kommt über die Adresse. */
    public function form(): void
    {
        $f = HydrantFilter::parse($_GET);
        $opt = OsmExport::options($_GET);
        $this->renderForm($f, $opt);
    }

    public function download(): void
    {
        $f = HydrantFilter::parse($_POST);
        $opt = OsmExport::options($_POST);
        $format = $this->post('format');
        if (!in_array($format, ['csv', 'xml'], true)) {
            flash('error', 'Bitte das Dateiformat wählen.');
            redirect('admin/export/osm?' . http_build_query(HydrantFilter::toQuery($f)));
        }
        $res = OsmExport::build(OsmExport::hydrants($f), $opt);
        if (!$res['nodes']) {
            flash('error', 'Es gibt nichts zu exportieren: Keiner der Hydranten hat Koordinaten.');
            redirect('admin/export/osm?' . http_build_query(HydrantFilter::toQuery($f) + $opt));
        }
        Audit::log('hydranten_osm_exportiert', 'hydrant', null, strtoupper($format) . ', ' . count($res['nodes']) . ' Knoten, ' . HydrantFilter::describe($f));
        $name = 'hydranten-osm-' . date('Y-m-d') . '.' . ($format === 'csv' ? 'csv' : 'osm');
        $body = $format === 'csv' ? OsmExport::csv($res['nodes']) : OsmExport::xml($res['nodes']);
        header('Content-Type: ' . ($format === 'csv' ? 'text/csv; charset=utf-8' : 'application/xml; charset=utf-8'));
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . strlen($body));
        echo $body;
    }

    private function renderForm(array $f, array $opt): void
    {
        $hydrants = OsmExport::hydrants($f);
        $this->render('admin/osm_export', [
            'title' => 'Export für OpenStreetMap',
            'f'     => $f,
            'opt'   => $opt,
            'desc'  => HydrantFilter::describe($f),
            'res'   => OsmExport::build($hydrants, $opt),
            'listUrl' => 'hydranten?' . http_build_query(HydrantFilter::toQuery($f)),
        ]);
    }
}
