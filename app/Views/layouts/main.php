<?php
/** @var \App\Core\View $this */
/** @var string $content  HTML déjà échappé par les gabarits enfants */
/** @var string|null $title */
/** @var array<string,mixed>|null $seo  description, canonical, robots, image, type, jsonld */
$seo = $seo ?? [];
$siteName = $this->t('app.name');
$fullTitle = (isset($title) && $title !== '' && $title !== $siteName) ? $title . ' — ' . $siteName : $siteName;
$navCategories = $this->shared('navCategories');
$navCategories = is_array($navCategories) ? $navCategories : [];
$description = (string) ($seo['description'] ?? '');
$canonical = (string) ($seo['canonical'] ?? '');
?>
<!DOCTYPE html>
<html lang="<?= $this->e($this->locale()) ?>" dir="<?= $this->e($this->direction()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->e($fullTitle) ?></title>
<?php if ($description !== ''): ?>
    <meta name="description" content="<?= $this->e($description) ?>">
<?php endif; ?>
<?php if (!empty($seo['robots'])): ?>
    <meta name="robots" content="<?= $this->e((string) $seo['robots']) ?>">
<?php endif; ?>
<?php if ($canonical !== ''): ?>
    <link rel="canonical" href="<?= $this->e($canonical) ?>">
    <meta property="og:url" content="<?= $this->e($canonical) ?>">
<?php endif; ?>
    <meta property="og:site_name" content="<?= $this->e($siteName) ?>">
    <meta property="og:title" content="<?= $this->e($fullTitle) ?>">
    <meta property="og:type" content="<?= $this->e((string) ($seo['type'] ?? 'website')) ?>">
    <meta property="og:locale" content="<?= $this->locale() === 'ar' ? 'ar_AR' : 'fr_FR' ?>">
<?php if ($description !== ''): ?>
    <meta property="og:description" content="<?= $this->e($description) ?>">
<?php endif; ?>
<?php if (!empty($seo['image'])): ?>
    <meta property="og:image" content="<?= $this->e((string) $seo['image']) ?>">
<?php endif; ?>
    <link rel="stylesheet" href="<?= $this->e($this->asset('css/app.css')) ?>">
    <script src="<?= $this->e($this->asset('js/site.js')) ?>" defer></script>
<?php if (!empty($seo['jsonld'])): ?>
    <script type="application/ld+json"><?= json_encode($seo['jsonld'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
<?php endif; ?>
</head>
<body class="site">
    <a class="skip-link" href="#main"><?= $this->e($this->t('a11y.skip')) ?></a>
    <header class="site-header">
        <div class="container header-row">
            <a class="brand" href="<?= $this->e($this->url('/')) ?>"><span class="brand-mark" aria-hidden="true"></span><?= $this->e($siteName) ?></a>
            <form class="search" role="search" method="get" action="<?= $this->e($this->url('/produits')) ?>">
                <label class="sr-only" for="site-search"><?= $this->e($this->t('search.label')) ?></label>
                <input id="site-search" name="q" type="search" maxlength="100" placeholder="<?= $this->e($this->t('search.placeholder')) ?>" autocomplete="off">
                <button type="submit" aria-label="<?= $this->e($this->t('common.search')) ?>"><?= $this->icon('search') ?></button>
            </form>
            <?= $this->partial('partials/lang-switch') ?>
        </div>
        <nav class="main-nav" aria-label="<?= $this->e($this->t('nav.menu')) ?>">
            <div class="container nav-row">
                <a href="<?= $this->e($this->url('/')) ?>"><?= $this->e($this->t('nav.home')) ?></a>
                <a href="<?= $this->e($this->url('/produits')) ?>"><?= $this->e($this->t('nav.products')) ?></a>
<?php foreach (array_slice($navCategories, 0, 8) as $category): ?>
                <a href="<?= $this->e($this->url('/categorie/' . $category['slug'])) ?>"><?= $this->e($this->localized($category, 'name')) ?></a>
<?php endforeach; ?>
            </div>
        </nav>
    </header>
    <main id="main">
        <?= $content ?>
    </main>
    <footer class="site-footer">
        <div class="container footer-grid">
            <div>
                <p class="footer-brand"><?= $this->e($siteName) ?></p>
                <p class="muted-light"><?= $this->e($this->t('footer.tagline')) ?></p>
            </div>
            <nav aria-label="<?= $this->e($this->t('footer.links')) ?>">
                <p class="footer-title"><?= $this->e($this->t('footer.links')) ?></p>
                <ul class="footer-links">
                    <li><a href="<?= $this->e($this->url('/produits')) ?>"><?= $this->e($this->t('nav.products')) ?></a></li>
<?php foreach (array_slice($navCategories, 0, 5) as $category): ?>
                    <li><a href="<?= $this->e($this->url('/categorie/' . $category['slug'])) ?>"><?= $this->e($this->localized($category, 'name')) ?></a></li>
<?php endforeach; ?>
                </ul>
            </nav>
        </div>
        <p class="footer-rights"><?= $this->e($this->t('footer.rights')) ?></p>
    </footer>
</body>
</html>
