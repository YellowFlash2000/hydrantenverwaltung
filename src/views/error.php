<?php /** @var int $code */ /** @var string $title */ /** @var string $message */ ?>
<div class="card card-narrow">
  <p class="error-code"><?= (int) $code ?></p>
  <h1><?= e($title) ?></h1>
  <p><?= e($message) ?></p>
  <p><a class="btn btn-secondary" href="<?= e(url('')) ?>">Zur Startseite</a></p>
</div>
