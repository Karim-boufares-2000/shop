<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $product  ligne issue de StorefrontRepository (colonnes "carte") */
$name = $this->localized($product, 'name');
$onSale = $product['promo_price'] !== null;
$inStock = (int) $product['available_stock'] > 0;
?>
<article class="product-card">
    <a class="product-card__link" href="<?= $this->e($this->url('/produit/' . $product['slug'])) ?>">
        <div class="product-card__media">
<?php if (!empty($product['main_image'])): ?>
            <img src="<?= $this->e($this->mediaUrl((string) $product['main_image'], true)) ?>" alt="<?= $this->e($name) ?>" width="400" height="400" loading="lazy" decoding="async">
<?php else: ?>
            <span class="product-card__placeholder"><?= $this->icon('image') ?></span>
<?php endif; ?>
<?php if ($onSale): ?>
            <span class="badge-promo">-<?= $this->e((string) $this->discountPercent((string) $product['price'], (string) $product['promo_price'])) ?>%</span>
<?php endif; ?>
<?php if (!$inStock): ?>
            <span class="badge-out"><?= $this->e($this->t('product.out_of_stock')) ?></span>
<?php endif; ?>
        </div>
        <div class="product-card__body">
            <h3 class="product-card__title"><?= $this->e($name) ?></h3>
            <p class="price">
<?php if ($onSale): ?>
                <span class="price-now"><?= $this->e($this->money((string) $product['promo_price'])) ?> <?= $this->e($this->t('currency.symbol')) ?></span>
                <s class="price-old"><?= $this->e($this->money((string) $product['price'])) ?></s>
<?php else: ?>
                <span class="price-now"><?= $this->e($this->money((string) $product['price'])) ?> <?= $this->e($this->t('currency.symbol')) ?></span>
<?php endif; ?>
            </p>
        </div>
    </a>
</article>
