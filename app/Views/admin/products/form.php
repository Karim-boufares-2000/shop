<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed>|null $product */
/** @var array<string,string> $values */
/** @var array<string,string> $errors */
/** @var list<array{id:int,label:string}> $categories */
/** @var list<array<string,mixed>> $images */
/** @var list<array<string,mixed>> $variants */
$editing = $product !== null;
$productId = $editing ? (int) $product['id'] : 0;
$action = $editing ? '/admin/products/' . $productId : '/admin/products';
?>
<div class="page-head">
    <h1><?= $this->e($this->t($editing ? 'product.edit' : 'product.new')) ?></h1>
    <a href="<?= $this->e($this->url('/admin/products')) ?>"><?= $this->e($this->t('common.back')) ?></a>
</div>
<?php if ($errors !== []): ?>
    <p class="alert" role="alert"><?= $this->e($this->t('common.form_errors')) ?></p>
<?php endif; ?>

<form method="post" action="<?= $this->e($this->url($action)) ?>" class="form-grid">
    <?= $this->csrfField() ?>
    <?= $this->partial('partials/form-field', ['name' => 'name_fr', 'label' => $this->t('product.name_fr'), 'value' => $values['name_fr'], 'error' => $errors['name_fr'] ?? null, 'attrs' => ['maxlength' => '200', 'required' => 'required']]) ?>
    <?= $this->partial('partials/form-field', ['name' => 'name_ar', 'label' => $this->t('product.name_ar'), 'value' => $values['name_ar'], 'error' => $errors['name_ar'] ?? null, 'attrs' => ['maxlength' => '200', 'dir' => 'rtl', 'lang' => 'ar']]) ?>
    <?= $this->partial('partials/form-select', ['name' => 'category_id', 'label' => $this->t('product.category'), 'value' => $values['category_id'], 'options' => $categories, 'error' => $errors['category_id'] ?? null, 'emptyLabel' => '—']) ?>
    <?= $this->partial('partials/form-field', ['name' => 'price', 'label' => $this->t('product.price') . ' (' . $this->t('currency.symbol') . ')', 'value' => $values['price'], 'error' => $errors['price'] ?? null, 'attrs' => ['inputmode' => 'decimal', 'required' => 'required', 'maxlength' => '14']]) ?>
    <?= $this->partial('partials/form-field', ['name' => 'promo_price', 'label' => $this->t('product.promo_price'), 'value' => $values['promo_price'], 'error' => $errors['promo_price'] ?? null, 'attrs' => ['inputmode' => 'decimal', 'maxlength' => '14']]) ?>
    <?= $this->partial('partials/form-field', ['name' => 'stock', 'label' => $this->t('product.stock'), 'value' => $values['stock'], 'type' => 'number', 'error' => $errors['stock'] ?? null, 'hint' => $this->t('product.stock_hint'), 'attrs' => ['min' => '0', 'max' => '1000000', 'required' => 'required']]) ?>
    <?= $this->partial('partials/form-field', ['name' => 'description_fr', 'label' => $this->t('product.description_fr'), 'value' => $values['description_fr'], 'type' => 'textarea', 'error' => $errors['description_fr'] ?? null, 'attrs' => ['maxlength' => '5000']]) ?>
    <?= $this->partial('partials/form-field', ['name' => 'description_ar', 'label' => $this->t('product.description_ar'), 'value' => $values['description_ar'], 'type' => 'textarea', 'error' => $errors['description_ar'] ?? null, 'attrs' => ['maxlength' => '5000', 'dir' => 'rtl', 'lang' => 'ar']]) ?>
    <?= $this->partial('partials/form-checkbox', ['name' => 'is_active', 'label' => $this->t('product.active'), 'checked' => $values['is_active'] === '1']) ?>
    <?= $this->partial('partials/form-checkbox', ['name' => 'is_featured', 'label' => $this->t('product.featured'), 'checked' => $values['is_featured'] === '1']) ?>
    <div class="form-actions">
        <button type="submit" class="button"><?= $this->e($this->t('common.save')) ?></button>
    </div>
</form>

<?php if (!$editing): ?>
    <p class="notice"><?= $this->e($this->t('product.save_first')) ?></p>
<?php else: ?>
    <section id="images" class="panel">
        <h2><?= $this->e($this->t('image.title')) ?></h2>
        <form method="post" action="<?= $this->e($this->url('/admin/products/' . $productId . '/images')) ?>" enctype="multipart/form-data" class="upload-form">
            <?= $this->csrfField() ?>
            <label for="f_images"><?= $this->e($this->t('image.upload')) ?></label>
            <input id="f_images" name="images[]" type="file" accept="image/jpeg,image/png,image/webp" multiple required>
            <small class="hint"><?= $this->e($this->t('image.help')) ?></small>
            <button type="submit" class="button"><?= $this->e($this->t('common.add')) ?></button>
        </form>
<?php if ($images === []): ?>
        <p class="muted"><?= $this->e($this->t('image.none')) ?></p>
<?php else: ?>
        <ul class="gallery">
<?php foreach ($images as $image): ?>
<?php $imageId = (int) $image['id']; ?>
            <li>
                <img src="<?= $this->e($this->mediaUrl((string) $image['file_id'], true)) ?>" alt="<?= $this->e($this->t('image.alt')) ?>" width="160" height="160" loading="lazy">
<?php if ((int) $image['is_main'] === 1): ?>
                <span class="badge badge-on"><?= $this->e($this->t('image.main')) ?></span>
<?php else: ?>
                <form method="post" action="<?= $this->e($this->url('/admin/products/' . $productId . '/images/' . $imageId . '/main')) ?>" class="inline-form">
                    <?= $this->csrfField() ?>
                    <button type="submit" class="link"><?= $this->e($this->t('image.set_main')) ?></button>
                </form>
<?php endif; ?>
                <form method="post" action="<?= $this->e($this->url('/admin/products/' . $productId . '/images/' . $imageId . '/delete')) ?>" class="inline-form" data-confirm="<?= $this->e($this->t('common.confirm_delete')) ?>">
                    <?= $this->csrfField() ?>
                    <button type="submit" class="link-danger"><?= $this->e($this->t('image.delete')) ?></button>
                </form>
            </li>
<?php endforeach; ?>
        </ul>
<?php endif; ?>
    </section>

    <section id="variants" class="panel">
        <h2><?= $this->e($this->t('variant.title')) ?></h2>
<?php if ($variants === []): ?>
        <p class="muted"><?= $this->e($this->t('variant.none')) ?></p>
<?php endif; ?>
<?php foreach ($variants as $variant): ?>
<?php $vid = (int) $variant['id']; $vbase = '/admin/products/' . $productId . '/variants/' . $vid; ?>
        <div class="variant-row">
            <form method="post" action="<?= $this->e($this->url($vbase)) ?>" class="variant-form">
                <?= $this->csrfField() ?>
                <div class="field"><label for="v<?= $vid ?>_label_fr"><?= $this->e($this->t('variant.label_fr')) ?></label>
                    <input id="v<?= $vid ?>_label_fr" name="label_fr" type="text" value="<?= $this->e((string) $variant['label_fr']) ?>" maxlength="100" required></div>
                <div class="field"><label for="v<?= $vid ?>_label_ar"><?= $this->e($this->t('variant.label_ar')) ?></label>
                    <input id="v<?= $vid ?>_label_ar" name="label_ar" type="text" value="<?= $this->e((string) ($variant['label_ar'] ?? '')) ?>" maxlength="100" dir="rtl" lang="ar"></div>
                <div class="field"><label for="v<?= $vid ?>_sku"><?= $this->e($this->t('variant.sku')) ?></label>
                    <input id="v<?= $vid ?>_sku" name="sku" type="text" value="<?= $this->e((string) ($variant['sku'] ?? '')) ?>" maxlength="64"></div>
                <div class="field"><label for="v<?= $vid ?>_price"><?= $this->e($this->t('variant.price_override')) ?></label>
                    <input id="v<?= $vid ?>_price" name="price_override" type="text" inputmode="decimal" value="<?= $this->e((string) ($variant['price_override'] ?? '')) ?>" maxlength="14"></div>
                <div class="field"><label for="v<?= $vid ?>_stock"><?= $this->e($this->t('variant.stock')) ?></label>
                    <input id="v<?= $vid ?>_stock" name="stock" type="number" min="0" max="1000000" value="<?= $this->e((string) $variant['stock']) ?>" required></div>
                <div class="field field-check"><input id="v<?= $vid ?>_active" name="is_active" type="checkbox" value="1"<?= (int) $variant['is_active'] === 1 ? ' checked' : '' ?>>
                    <label for="v<?= $vid ?>_active"><?= $this->e($this->t('variant.active')) ?></label></div>
                <div class="form-actions"><button type="submit" class="button"><?= $this->e($this->t('common.save')) ?></button></div>
            </form>
            <form method="post" action="<?= $this->e($this->url($vbase . '/delete')) ?>" class="inline-form" data-confirm="<?= $this->e($this->t('common.confirm_delete')) ?>">
                <?= $this->csrfField() ?>
                <button type="submit" class="link-danger"><?= $this->e($this->t('common.delete')) ?></button>
            </form>
        </div>
<?php endforeach; ?>

        <h3><?= $this->e($this->t('variant.add')) ?></h3>
        <form method="post" action="<?= $this->e($this->url('/admin/products/' . $productId . '/variants')) ?>" class="variant-form">
            <?= $this->csrfField() ?>
            <div class="field"><label for="nv_label_fr"><?= $this->e($this->t('variant.label_fr')) ?></label>
                <input id="nv_label_fr" name="label_fr" type="text" maxlength="100" required></div>
            <div class="field"><label for="nv_label_ar"><?= $this->e($this->t('variant.label_ar')) ?></label>
                <input id="nv_label_ar" name="label_ar" type="text" maxlength="100" dir="rtl" lang="ar"></div>
            <div class="field"><label for="nv_sku"><?= $this->e($this->t('variant.sku')) ?></label>
                <input id="nv_sku" name="sku" type="text" maxlength="64"></div>
            <div class="field"><label for="nv_price"><?= $this->e($this->t('variant.price_override')) ?></label>
                <input id="nv_price" name="price_override" type="text" inputmode="decimal" maxlength="14"></div>
            <div class="field"><label for="nv_stock"><?= $this->e($this->t('variant.stock')) ?></label>
                <input id="nv_stock" name="stock" type="number" min="0" max="1000000" value="0" required></div>
            <div class="field field-check"><input id="nv_active" name="is_active" type="checkbox" value="1" checked>
                <label for="nv_active"><?= $this->e($this->t('variant.active')) ?></label></div>
            <div class="form-actions"><button type="submit" class="button"><?= $this->e($this->t('common.add')) ?></button></div>
        </form>
    </section>
<?php endif; ?>
