<?php
declare(strict_types=1);

/** Revisionen in der Anwendung eintragen (ab Rolle Revisor) und Mangelfotos ausliefern. */
final class RevisionController extends Controller
{
    public function create(array $params): void
    {
        $h = Hydrant::find((int) ($params['id'] ?? 0));
        if ($h === null) {
            $this->fail(404, 'Nicht gefunden', 'Diesen Hydranten gibt es nicht.');
            return;
        }
        if ((int) $h['aktiv'] !== 1) {
            $this->fail(400, 'Nicht möglich', 'Deaktivierte Hydranten werden nicht geprüft.');
            return;
        }
        [$values, $errors] = Revision::readForm($_POST, $h);
        $photos = ['files' => [], 'errors' => []];
        if ($values['ergebnis'] === Revision::DEFECT) {
            $photos = Revision::collectPhotos('fotos');
            $errors = array_merge($errors, $photos['errors']);
        }
        $names = [];
        if (!$errors && $photos['files']) {
            [$names, $photoErrors] = Revision::storePhotos($photos['files']);
            $errors = array_merge($errors, $photoErrors);
        }
        if ($errors) {
            $form = ['ergebnis' => (string) ($_POST['ergebnis'] ?? ''), 'maengel' => $values['defects'], 'bemerkung' => (string) ($values['bemerkung'] ?? '')];
            (new HydrantController())->renderShow($h, $form, $errors, 422);
            return;
        }
        // Die Revision zählt für den aktiven Prüfdurchgang, wenn die Tour des Hydranten dazu gehört; sonst wird sie ohne Durchgang gespeichert.
        $campaign = Campaign::active();
        $tour = $h['tour_id'] === null ? null : Tour::find((int) $h['tour_id']);
        if ($campaign === null || $tour === null || CampaignTour::forTour((int) $campaign['id'], (int) $tour['id']) === null) {
            $campaign = null;
        }
        $id = Revision::create($h, $values, 'app', (int) (Auth::user()['id'] ?? 0) ?: null, null, $tour, $campaign, $names);
        Audit::log('revision_erfasst', 'hydrant', (int) $h['id'], $h['kennung'] . ': ' . ($values['ergebnis'] === Revision::DEFECT ? 'Mangel' : 'in Ordnung') . ' (#' . $id . ')');
        if ($campaign === null) {
            flash('warning', 'Die Revision für „' . $h['kennung'] . '“ wurde gespeichert, gehört aber zu keinem Prüfdurchgang. Sie steht im Verlauf des Hydranten und zählt nicht im Fortschritt der Touren. Ein Mangel erscheint in der Mängelübersicht unter „Ohne Prüfdurchgang“, bis der Hydrant wieder als in Ordnung geprüft wird.');
        } else {
            flash('success', 'Die Revision für „' . $h['kennung'] . '“ wurde gespeichert.');
        }
        redirect('hydranten/' . (int) $h['id'] . '#pruefung');
    }

    /** Foto eines Mangels, nur für angemeldete Benutzer. */
    public function image(array $params): void
    {
        $row = Db::one('SELECT datei FROM {revision_images} WHERE id = ?', [(int) ($params['id'] ?? 0)]);
        if ($row === null || !Images::serve((string) $row['datei'], (string) ($params['size'] ?? ''))) {
            $this->fail(404, 'Nicht gefunden', 'Dieses Bild gibt es nicht.');
        }
    }
}
