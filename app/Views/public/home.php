<?php
/** @var \App\Core\View $this */
/** @var list<array<string,mixed>> $featured */
/** @var list<array<string,mixed>> $latest */
/** @var list<array<string,mixed>> $promo */
/** @var list<array<string,mixed>> $categories */
$hasProducts = $featured !== [] || $latest !== [] || $promo !== [];
?>
<section class="hero">
    <div class="container hero-inner">
        <h1><?= $this->e($this->t('home.hero.title')) ?></h1>
        <p class="hero-sub"><?= $this->e($this->t('home.hero.subtitle')) ?></p>
        <a class="btn btn-light btn-lg" href="<?= $this->e($this->url('/produits')) ?>"><?= $this->e($this->t('home.hero.cta')) ?></a>
    </div>
</section>

<section class="container perks" aria-label="<?= $this->e($this->t('home.perks')) ?>">
    <div class="perk"><?= $this->icon('truck') ?><span><?= $this->e($this->t('home.perk.delivery')) ?></span></div>
    <div class="perk"><?= $this->icon('cash') ?><span><?= $this->e($this->t('home.perk.cod')) ?></span></div>
    <div class="perk"><?= $this->icon('check') ?><span><?= $this->e($this->t('home.perk.order')) ?></span></div>
</section>

<?php if ($categories !== []): ?>
<section class="container section">
    <h2 class="section-title"><?= $this->e($this->t('home.section.categories')) ?></h2>
    <ul class="cat-grid">
<?php foreach (array_slice($categories, 0, 8) as $category): ?>
        <li>
            <a class="cat-tile" href="<?= $this->e($this->url('/categorie/' . $category['slug'])) ?>">
                <span class="cat-tile__name"><?= $this->e($this->localized($category, 'name')) ?></span>
                <span class="cat-tile__count"><?= $this->e($this->t('catalog.results', ['count' => (int) $category['products_count']])) ?></span>
            </a>
        </li>
<?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<?php
$sections = [
    ['key' => 'home.section.featured', 'items' => $featured, 'link' => '/produits'],
    ['key' => 'home.section.latest', 'items' => $latest, 'link' => '/produits'],
    ['key' => 'home.section.promo', 'items' => $promo, 'link' => '/produits?promo=1'],
];
?>
<?php foreach ($sections as $section): ?>
<?php if ($section['items'] !== []): ?>
<section class="container section">
    <div class="section-head">
        <h2 class="section-title"><?= $this->e($this->t($section['key'])) ?></h2>
        <a href="<?= $this->e($this->url($section['link'])) ?>"><?= $this->e($this->t('home.view_all')) ?></a>
    </div>
    <div class="products-grid">
<?php foreach ($section['items'] as $product): ?>
        <?= $this->partial('partials/product-card', ['product' => $product]) ?>
<?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
<?php endforeach; ?>

<?php if (!$hasProducts): ?>
<section class="container section empty-state">
    <p><?= $this->e($this->t('home.empty')) ?></p>
</section>
<?php endif; ?>
