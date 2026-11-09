<?php
/** @var \App\Core\View $this */
/** @var string $content  HTML déjà échappé par les gabarits enfants */
/** @var string|null $title */
/** @var array<string,mixed>|null $admin */
/** @var array<string,mixed>|null $flash  key/params/type ou text/type */
?>
<!DOCTYPE html>
<html lang="<?= $this->e($this->locale()) ?>" dir="<?= $this->e($this->direction()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $this->e($title ?? $this->t('app.name')) ?></title>
    <link rel="stylesheet" href="<?= $this->e($this->asset('css/app.css')) ?>">
    <script src="<?= $this->e($this->asset('js/admin.js')) ?>" defer></script>
</head>
<body class="admin">
    <a class="skip-link" href="#main"><?= $this->e($this->t('a11y.skip')) ?></a>
    <header class="site-header">
        <span class="brand"><?= $this->e($this->t('app.name')) ?> · <?= $this->e($this->t('admin.area')) ?></span>
        <?= $this->partial('partials/lang-switch') ?>
        <?php if (isset($admin)): ?>
            <form method="post" action="<?= $this->e($this->url('/admin/logout')) ?>" class="inline-form">
                <?= $this->csrfField() ?>
                <button type="submit"><?= $this->e($this->t('admin.logout')) ?></button>
            </form>
        <?php endif; ?>
    </header>
    <?php if (isset($admin)): ?>
        <nav class="admin-nav" aria-label="<?= $this->e($this->t('admin.nav.label')) ?>">
            <a href="<?= $this->e($this->url('/admin')) ?>"><?= $this->e($this->t('admin.nav.dashboard')) ?></a>
            <a href="<?= $this->e($this->url('/admin/categories')) ?>"><?= $this->e($this->t('admin.nav.categories')) ?></a>
            <a href="<?= $this->e($this->url('/admin/products')) ?>"><?= $this->e($this->t('admin.nav.products')) ?></a>
        </nav>
    <?php endif; ?>
    <main id="main" class="container">
        <?php if (!empty($flash)): ?>
            <?php $flashText = isset($flash['text']) ? (string) $flash['text'] : $this->t((string) ($flash['key'] ?? ''), (array) ($flash['params'] ?? [])); ?>
            <p class="<?= ($flash['type'] ?? '') === 'error' ? 'alert' : 'notice' ?>" role="<?= ($flash['type'] ?? '') === 'error' ? 'alert' : 'status' ?>"><?= $this->e($flashText) ?></p>
        <?php endif; ?>
        <?= $content ?>
    </main>
</body>
</html>
