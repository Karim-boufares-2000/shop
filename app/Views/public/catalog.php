<?php
/** @var \App\Core\View $this */
/** @var string $heading */
/** @var list<array{label:string,path:?string}> $breadcrumbs */
/** @var list<array<string,mixed>> $items */
/** @var int $total */
/** @var int $page */
/** @var int $pages */
/** @var \App\Support\ListingFilters $filters */
/** @var string $basePath */
/** @var array<string,scalar> $query */
/** @var bool $showCategoryFilter */
/** @var list<array{slug:string,label:string}> $categoryOptions */
$sortKeys = $filters->q !== '' ? ['relevance', 'newest', 'price_asc', 'price_desc', 'name'] : ['newest', 'price_asc', 'price_desc', 'name'];
?>
<div class="container section">
    <?= $this->partial('partials/breadcrumb', ['items' => $breadcrumbs]) ?>
    <div class="page-head">
        <h1><?= $this->e($heading) ?></h1>
        <p class="muted"><?= $this->e($this->t('catalog.results', ['count' => $total])) ?></p>
    </div>

    <form class="filter-bar" method="get" action="<?= $this->e($this->url($basePath)) ?>">
        <div class="field">
            <label for="f_q"><?= $this->e($this->t('common.search')) ?></label>
            <input id="f_q" name="q" type="search" value="<?= $this->e($filters->q) ?>" maxlength="100">
        </div>
<?php if ($showCategoryFilter): ?>
        <div class="field">
            <label for="f_category"><?= $this->e($this->t('catalog.filter.category')) ?></label>
            <select id="f_category" name="category">
                <option value=""><?= $this->e($this->t('common.all')) ?></option>
<?php foreach ($categoryOptions as $option): ?>
                <option value="<?= $this->e($option['slug']) ?>"<?= $filters->category === $option['slug'] ? ' selected' : '' ?>><?= $this->e($option['label']) ?></option>
<?php endforeach; ?>
            </select>
        </div>
<?php endif; ?>
        <div class="field">
            <label for="f_min"><?= $this->e($this->t('catalog.filter.min_price')) ?></label>
            <input id="f_min" name="min_price" type="text" inputmode="decimal" value="<?= $this->e((string) $filters->minPrice) ?>" maxlength="14">
        </div>
        <div class="field">
            <label for="f_max"><?= $this->e($this->t('catalog.filter.max_price')) ?></label>
            <input id="f_max" name="max_price" type="text" inputmode="decimal" value="<?= $this->e((string) $filters->maxPrice) ?>" maxlength="14">
        </div>
        <div class="field">
            <label for="f_sort"><?= $this->e($this->t('common.sort')) ?></label>
            <select id="f_sort" name="sort">
<?php foreach ($sortKeys as $key): ?>
                <option value="<?= $this->e($key) ?>"<?= $filters->effectiveSort() === $key ? ' selected' : '' ?>><?= $this->e($this->t('catalog.sort.' . $key)) ?></option>
<?php endforeach; ?>
            </select>
        </div>
        <div class="field field-check">
            <input id="f_stock" name="in_stock" type="checkbox" value="1"<?= $filters->inStock ? ' checked' : '' ?>>
            <label for="f_stock"><?= $this->e($this->t('catalog.filter.in_stock')) ?></label>
        </div>
        <div class="field field-check">
            <input id="f_promo" name="promo" type="checkbox" value="1"<?= $filters->onlyPromo ? ' checked' : '' ?>>
            <label for="f_promo"><?= $this->e($this->t('catalog.filter.promo')) ?></label>
        </div>
        <div class="form-actions filter-actions">
            <button type="submit" class="btn btn-primary"><?= $this->e($this->t('catalog.filter.apply')) ?></button>
            <a class="btn btn-outline" href="<?= $this->e($this->url($basePath)) ?>"><?= $this->e($this->t('catalog.filter.reset')) ?></a>
        </div>
    </form>

<?php if ($items === []): ?>
    <div class="empty-state"><p><?= $this->e($this->t('catalog.no_results')) ?></p></div>
<?php else: ?>
    <div class="products-grid">
<?php foreach ($items as $product): ?>
        <?= $this->partial('partials/product-card', ['product' => $product]) ?>
<?php endforeach; ?>
    </div>
    <?= $this->partial('partials/pagination', ['page' => $page, 'pages' => $pages, 'path' => $basePath, 'query' => $query]) ?>
<?php endif; ?>
</div>
