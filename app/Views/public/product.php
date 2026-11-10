<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $product */
/** @var list<array<string,mixed>> $images */
/** @var list<array<string,mixed>> $variants */
/** @var list<array<string,mixed>> $similar */
/** @var list<array{label:string,path:?string}> $breadcrumbs */
$name = $this->localized($product, 'name');
$description = $this->localized($product, 'description');
$onSale = $product['promo_price'] !== null;
$inStock = (int) $product['available_stock'] > 0;
$currency = $this->t('currency.symbol');
$categoryName = $this->localized(['name_fr' => $product['category_name_fr'], 'name_ar' => $product['category_name_ar']], 'name');
?>
<div class="container section">
    <?= $this->partial('partials/breadcrumb', ['items' => $breadcrumbs]) ?>
    <article class="product-layout">
        <div class="gallery-box">
<?php if ($images !== []): ?>
            <img id="gallery-main" class="gallery-main" src="<?= $this->e($this->mediaUrl((string) $images[0]['file_id'])) ?>" alt="<?= $this->e($name) ?>" width="1000" height="1000" decoding="async">
<?php if (count($images) > 1): ?>
            <ul class="thumbs">
<?php foreach ($images as $index => $image): ?>
                <li>
                    <button type="button" class="thumb-btn" data-gallery-src="<?= $this->e($this->mediaUrl((string) $image['file_id'])) ?>" aria-label="<?= $this->e($this->t('product.gallery.show', ['n' => $index + 1])) ?>"<?= $index === 0 ? ' aria-current="true"' : '' ?>>
                        <img src="<?= $this->e($this->mediaUrl((string) $image['file_id'], true)) ?>" alt="" width="80" height="80" loading="lazy">
                    </button>
                </li>
<?php endforeach; ?>
            </ul>
<?php endif; ?>
<?php else: ?>
            <div class="gallery-empty"><?= $this->icon('image') ?></div>
<?php endif; ?>
        </div>

        <div class="product-info">
            <p class="muted"><a href="<?= $this->e($this->url('/categorie/' . $product['category_slug'])) ?>"><?= $this->e($categoryName) ?></a></p>
            <h1><?= $this->e($name) ?></h1>
            <p class="price price-lg">
<?php if ($onSale): ?>
                <span class="price-now"><?= $this->e($this->money((string) $product['promo_price'])) ?> <?= $this->e($currency) ?></span>
                <s class="price-old"><?= $this->e($this->money((string) $product['price'])) ?></s>
                <span class="badge-promo badge-inline">-<?= $this->e((string) $this->discountPercent((string) $product['price'], (string) $product['promo_price'])) ?>%</span>
<?php else: ?>
                <span class="price-now"><?= $this->e($this->money((string) $product['price'])) ?> <?= $this->e($currency) ?></span>
<?php endif; ?>
            </p>
            <p>
                <span class="badge <?= $inStock ? 'badge-on' : 'badge-off' ?>"><?= $this->e($this->t($inStock ? 'product.in_stock' : 'product.out_of_stock')) ?></span>
            </p>

<?php if ($variants !== []): ?>
            <section aria-labelledby="options-title">
                <h2 class="h3" id="options-title"><?= $this->e($this->t('product.options')) ?></h2>
                <ul class="variant-list">
<?php foreach ($variants as $variant): ?>
                    <li class="<?= (int) $variant['stock'] > 0 ? '' : 'is-out' ?>">
                        <span><?= $this->e($this->localized($variant, 'label')) ?></span>
                        <span class="muted">
                            <?= $this->e($this->money((string) ($variant['price_override'] ?? $product['promo_price'] ?? $product['price']))) ?> <?= $this->e($currency) ?>
                            · <?= $this->e($this->t((int) $variant['stock'] > 0 ? 'product.in_stock' : 'product.out_of_stock')) ?>
                        </span>
                    </li>
<?php endforeach; ?>
                </ul>
            </section>
<?php endif; ?>

<?php if ($description !== ''): ?>
            <section aria-labelledby="desc-title">
                <h2 class="h3" id="desc-title"><?= $this->e($this->t('product.description')) ?></h2>
                <p class="description"><?= $this->text($description) ?></p>
            </section>
<?php endif; ?>
        </div>
    </article>

<?php if ($similar !== []): ?>
    <section class="section">
        <h2 class="section-title"><?= $this->e($this->t('product.similar')) ?></h2>
        <div class="products-grid">
<?php foreach ($similar as $item): ?>
            <?= $this->partial('partials/product-card', ['product' => $item]) ?>
<?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
</div>
