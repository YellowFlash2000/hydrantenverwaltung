<?php
declare(strict_types=1);

final class HomeController extends Controller
{
    public function index(): void
    {
        $c = Db::one(
            'SELECT SUM(CASE WHEN aktiv = 1 THEN 1 ELSE 0 END) AS active, SUM(CASE WHEN aktiv = 0 THEN 1 ELSE 0 END) AS inactive,'
            . ' SUM(CASE WHEN aktiv = 1 AND (lat IS NULL OR lon IS NULL) THEN 1 ELSE 0 END) AS no_coords FROM {hydrants}'
        ) ?? [];
        $campaign = Campaign::active();
        $tours = [];
        $progress = [];
        if ($campaign !== null) {
            $tours = CampaignTour::forCampaign((int) $campaign['id']);
            $progress = CampaignTour::progress((int) $campaign['id']);
        }
        $this->render('home', [
            'campaign' => $campaign,
            'tours'    => $tours,
            'progress' => $progress,
            'title'  => 'Start',
            'user'   => Auth::user(),
            'counts' => [
                'active'    => (int) ($c['active'] ?? 0),
                'inactive'  => (int) ($c['inactive'] ?? 0),
                'no_coords' => (int) ($c['no_coords'] ?? 0),
            ],
        ]);
    }
}
