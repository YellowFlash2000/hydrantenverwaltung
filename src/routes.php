<?php
declare(strict_types=1);

// [Methode, Pfad, [Controller, Aktion], Mindestrolle]
// Mindestrolle: null = oeffentlich, 'login' = jeder angemeldete Benutzer, sonst viewer|reviser|editor|admin
return [
    ['GET',  '/',                          [HomeController::class, 'index'],              'viewer'],

    // Hydranten
    ['GET',  '/hydranten',                       [HydrantController::class, 'index'],       'viewer'],
    ['GET',  '/hydranten/neu',                   [HydrantController::class, 'createForm'],  'editor'],
    ['POST', '/hydranten/neu',                   [HydrantController::class, 'create'],      'editor'],
    ['GET',  '/hydranten/{id}',                  [HydrantController::class, 'show'],        'viewer'],
    ['GET',  '/hydranten/{id}/bearbeiten',       [HydrantController::class, 'editForm'],    'editor'],
    ['POST', '/hydranten/{id}/bearbeiten',       [HydrantController::class, 'update'],      'editor'],
    ['POST', '/hydranten/{id}/aktiv',            [HydrantController::class, 'toggleActive'], 'editor'],
    ['POST', '/hydranten/{id}/bilder',           [HydrantController::class, 'addImages'],   'editor'],
    ['POST', '/hydranten/{id}/bilder/{iid}',     [HydrantController::class, 'imageAction'], 'editor'],
    ['POST', '/hydranten/{id}/revision',         [RevisionController::class, 'create'],     'reviser'],
    ['GET',  '/revbild/{id}/{size}',             [RevisionController::class, 'image'],      'viewer'],
    ['GET',  '/bild/{id}/{size}',                [HydrantController::class, 'image'],       'viewer'],

    // Tour-Link ohne Anmeldung (der geheime Link ist der Zugang)
    ['GET',  '/t/{token}',                       [TourLinkController::class, 'show'],       null],
    ['POST', '/t/{token}',                       [TourLinkController::class, 'names'],      null],
    ['GET',  '/t/{token}/h/{id}',                [TourLinkController::class, 'hydrantPage'], null],
    ['POST', '/t/{token}/h/{id}',                [TourLinkController::class, 'save'],       null],
    ['GET',  '/t/{token}/bild/{id}/{size}',      [TourLinkController::class, 'image'],      null],

    // Anmeldung und Passwort
    ['GET',  '/login',                     [AuthController::class, 'loginForm'],          null],
    ['POST', '/login',                     [AuthController::class, 'login'],              null],
    ['POST', '/logout',                    [AuthController::class, 'logout'],             'login'],
    ['GET',  '/passwort/vergessen',        [AuthController::class, 'forgotForm'],         null],
    ['POST', '/passwort/vergessen',        [AuthController::class, 'forgot'],             null],
    ['GET',  '/passwort/setzen/{token}',   [AuthController::class, 'setPasswordForm'],    null],
    ['POST', '/passwort/setzen/{token}',   [AuthController::class, 'setPassword'],        null],
    ['GET',  '/passwort',                  [AuthController::class, 'changePasswordForm'], 'login'],
    ['POST', '/passwort',                  [AuthController::class, 'changePassword'],     'login'],

    // Darstellung (oeffentlich, wird auch auf der Login-Seite gebraucht)
    ['GET',  '/theme.css',                 [BrandingController::class, 'themeCss'],       null],
    ['GET',  '/branding/logo',             [BrandingController::class, 'logo'],           null],

    // Administration
    ['GET',  '/admin/benutzer',            [UserController::class, 'index'],              'admin'],
    ['GET',  '/admin/benutzer/neu',        [UserController::class, 'createForm'],         'admin'],
    ['POST', '/admin/benutzer/neu',        [UserController::class, 'create'],             'admin'],
    ['GET',  '/admin/benutzer/{id}',       [UserController::class, 'editForm'],           'admin'],
    ['POST', '/admin/benutzer/{id}',       [UserController::class, 'update'],             'admin'],
    ['POST', '/admin/benutzer/{id}/link',  [UserController::class, 'sendLink'],           'admin'],

    ['GET',  '/admin/import',                          [ImportController::class, 'index'],          'admin'],
    ['GET',  '/admin/import/muster-hydranten.csv',     [ImportController::class, 'sampleHydrants'], 'admin'],
    ['GET',  '/admin/import/muster-bilder.csv',        [ImportController::class, 'sampleImages'],   'admin'],
    ['POST', '/admin/import/hydranten',                [ImportController::class, 'hydrantsUpload'], 'admin'],
    ['POST', '/admin/import/hydranten/ausfuehren',     [ImportController::class, 'hydrantsRun'],    'admin'],
    ['POST', '/admin/import/bilder',                   [ImportController::class, 'imagesUpload'],   'admin'],
    ['POST', '/admin/import/bilder/ausfuehren',        [ImportController::class, 'imagesRun'],      'admin'],
    ['GET',  '/admin/osm',                             [OsmController::class, 'index'],             'admin'],
    ['POST', '/admin/osm/datei',                       [OsmController::class, 'fileUpload'],        'admin'],
    ['GET',  '/admin/osm/csv/{token}',                 [OsmController::class, 'download'],         'admin'],
    ['GET',  '/admin/export/hydranten.csv',            [ImportController::class, 'export'],         'admin'],
    ['GET',  '/admin/protokoll',                       [AuditController::class, 'index'],           'admin'],
    ['GET',  '/admin/protokoll.csv',                   [AuditController::class, 'export'],          'admin'],
    ['GET',  '/admin/export/osm',                      [OsmExportController::class, 'form'],        'admin'],
    ['POST', '/admin/export/osm',                      [OsmExportController::class, 'download'],    'admin'],

    // Prüfdurchgänge und Touren
    ['GET',  '/pruefungen',                    [CampaignController::class, 'index'],        'editor'],
    ['GET',  '/pruefungen/neu',                [CampaignController::class, 'createForm'],   'editor'],
    ['POST', '/pruefungen/neu',                [CampaignController::class, 'create'],       'editor'],
    ['GET',  '/pruefungen/{id}',               [CampaignController::class, 'show'],         'editor'],
    ['POST', '/pruefungen/{id}/touren',        [CampaignController::class, 'setTours'],     'editor'],
    ['POST', '/pruefungen/{id}/link/{ct}',     [CampaignController::class, 'link'],         'editor'],
    ['POST', '/pruefungen/{id}/mail/{ct}',     [CampaignController::class, 'mail'],         'editor'],
    ['POST', '/pruefungen/{id}/beenden',       [CampaignController::class, 'end'],          'editor'],
    ['POST', '/pruefungen/{id}/oeffnen',       [CampaignController::class, 'reopen'],       'editor'],
    ['GET',  '/touren',                        [TourController::class, 'index'],            'editor'],
    ['GET',  '/touren/neu',                    [TourController::class, 'createForm'],       'editor'],
    ['POST', '/touren/neu',                    [TourController::class, 'create'],           'editor'],
    ['GET',  '/touren/{id}',                   [TourController::class, 'show'],             'editor'],
    ['GET',  '/touren/{id}/bearbeiten',        [TourController::class, 'editForm'],         'editor'],
    ['POST', '/touren/{id}/bearbeiten',        [TourController::class, 'update'],           'editor'],
    ['POST', '/touren/{id}/loeschen',          [TourController::class, 'delete'],           'editor'],
    ['POST', '/hydranten/zuweisen',            [TourController::class, 'assign'],           'editor'],

    ['GET',  '/maengel',                       [DefectReportController::class, 'index'],    'editor'],
    ['GET',  '/maengel/export.csv',            [DefectReportController::class, 'export'],   'editor'],

    ['GET',  '/admin/stammdaten',                      [StammdatenController::class, 'index'],      'admin'],
    ['POST', '/admin/stammdaten/typen',                [StammdatenController::class, 'saveTypes'],  'admin'],
    ['POST', '/admin/stammdaten/positionen',           [StammdatenController::class, 'savePositions'], 'admin'],
    ['GET',  '/admin/maengel',                         [DefectController::class, 'index'],          'admin'],
    ['POST', '/admin/maengel',                         [DefectController::class, 'save'],           'admin'],

    ['GET',  '/admin/einstellungen',           [SettingsController::class, 'index'],      'admin'],
    ['POST', '/admin/einstellungen',           [SettingsController::class, 'save'],       'admin'],
    ['POST', '/admin/einstellungen/testmail',  [SettingsController::class, 'testMail'],   'admin'],
];
