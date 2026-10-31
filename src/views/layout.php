<?php
/** @var string $content */
/** @var string $title */
/** @var bool $wide */
/** @var string[] $styles */
/** @var string[] $scripts */
$app = Settings::get('app_name');
$logo = Settings::get('logo_file') !== '' ? url('branding/logo') . '?v=' . rawurlencode(Settings::get('logo_file')) : asset('img/logo-default.svg');
$user = Auth::user();
$theme = Settings::get('theme_version');
?><!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= e($title !== '' ? $title . ' – ' . $app : $app) ?></title>
  <link rel="icon" href="<?= e(asset('img/logo-default.svg')) ?>" type="image/svg+xml">
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
  <link rel="stylesheet" href="<?= e(url('theme.css') . '?v=' . rawurlencode($theme)) ?>">
  <?php foreach ($styles as $css): ?><link rel="stylesheet" href="<?= e(asset($css)) ?>">
  <?php endforeach; ?>
  <script src="<?= e(asset('js/app.js')) ?>" defer></script>
  <?php foreach ($scripts as $js): ?><script src="<?= e(asset($js)) ?>" defer></script>
  <?php endforeach; ?>
</head>
<body>
<header class="site-header">
  <div class="container container-wide header-inner">
    <a class="brand" href="<?= e(url('')) ?>">
      <img src="<?= e($logo) ?>" alt="" class="brand-logo">
      <span class="brand-name"><?= e($app) ?></span>
    </a>
    <?php if ($user !== null): ?>
      <button type="button" class="nav-toggle" aria-label="Menü" aria-expanded="false" aria-controls="main-nav">☰</button>
      <nav id="main-nav" class="main-nav">
        <a href="<?= e(url('')) ?>">Start</a>
        <a href="<?= e(url('hydranten')) ?>">Hydranten</a>
        <?php if (Auth::hasRole('editor')): ?>
          <a href="<?= e(url('touren')) ?>">Touren</a>
          <a href="<?= e(url('maengel')) ?>">Mängel</a>
        <?php endif; ?>
        <?php if (Auth::hasRole('admin')): ?>
          <a href="<?= e(url('admin/benutzer')) ?>">Benutzer</a>
          <a href="<?= e(url('admin/stammdaten')) ?>">Stammdaten</a>
          <a href="<?= e(url('admin/import')) ?>">Import/Export</a>
          <a href="<?= e(url('admin/protokoll')) ?>">Protokoll</a>
          <a href="<?= e(url('admin/einstellungen')) ?>">Einstellungen</a>
        <?php endif; ?>
        <span class="nav-sep"></span>
        <a href="<?= e(url('passwort')) ?>" class="nav-user" title="Passwort ändern"><?= e($user['name']) ?></a>
        <form method="post" action="<?= e(url('logout')) ?>" class="nav-logout">
          <?= csrf_field() ?>
          <button type="submit" class="linklike">Abmelden</button>
        </form>
      </nav>
    <?php endif; ?>
  </div>
</header>

<main class="container<?= $wide ? ' container-wide' : '' ?>">
  <?php foreach (flashes() as [$type, $msg]): ?>
    <div class="alert alert-<?= e($type) ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>"><?= e($msg) ?></div>
  <?php endforeach; ?>
  <?= $content ?>
</main>

<footer class="site-footer">
  <div class="container container-wide"><?= e($app) ?> · <?= date('Y') ?></div>
</footer>
</body>
</html>
