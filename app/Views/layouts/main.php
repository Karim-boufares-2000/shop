<?php
/** @var \App\Core\View $this */
/** @var string $content  HTML déjà échappé par les gabarits enfants */
/** @var string|null $title */
?>
<!DOCTYPE html>
<html lang="<?= $this->e($this->locale()) ?>" dir="<?= $this->e($this->direction()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->e($title ?? $this->t('app.name')) ?></title>
    <link rel="stylesheet" href="<?= $this->e($this->asset('css/app.css')) ?>">
</head>
<body>
    <a class="skip-link" href="#main"><?= $this->e($this->t('a11y.skip')) ?></a>
    <header class="site-header">
        <a class="brand" href="<?= $this->e($this->url('/')) ?>"><?= $this->e($this->t('app.name')) ?></a>
        <?= $this->partial('partials/lang-switch') ?>
    </header>
    <main id="main">
        <?= $content ?>
    </main>
    <footer class="site-footer">
        <p><?= $this->e($this->t('footer.rights')) ?></p>
    </footer>
</body>
</html>
