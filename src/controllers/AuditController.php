<?php
declare(strict_types=1);

/** Protokoll anzeigen und exportieren (nur Admin). */
final class AuditController extends Controller
{
    public function index(): void
    {
        AuditLog::cleanup();
        $f = AuditLog::parse($_GET);
        $total = AuditLog::count($f);
        $pages = max(1, (int) ceil($total / AuditLog::PER_PAGE));
        $f['seite'] = min($f['seite'], $pages);
        $rows = AuditLog::rows($f, AuditLog::PER_PAGE, ($f['seite'] - 1) * AuditLog::PER_PAGE);
        $this->render('admin/audit', [
            'title'  => 'Protokoll',
            'f'      => $f,
            'rows'   => $rows,
            'objects' => AuditLog::objects($rows),
            'total'  => $total,
            'pages'  => $pages,
            'users'  => AuditLog::users(),
            'all'    => (int) Db::val('SELECT COUNT(*) FROM {audit_log}'),
            'retention' => AuditLog::retentionMonths(),
        ]);
    }

    public function export(): void
    {
        $f = AuditLog::parse($_GET);
        $body = AuditLog::csv($f);
        Audit::log('protokoll_exportiert', 'audit', null, AuditLog::count($f) . ' Einträge');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="protokoll-' . date('Y-m-d') . '.csv"');
        header('Content-Length: ' . strlen($body));
        echo $body;
    }
}
