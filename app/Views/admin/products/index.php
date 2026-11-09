<?php
/** @var \App\Core\View $this */
/** @var list<array<string,mixed>> $items */
/** @var int $total */
/** @var int $page */
/** @var int $pages */
/** @var array<string,scalar> $query */
/** @var array<string,string> $filters */
/** @var list<string> $sorts */
/** @var list<array{id:int,label:string}> $categories */
$currency = $this->t('currency.symbol');
?>
<div class="page-head">
    <h1><?= $this->e($this->t('product.title')) ?> <span class="muted">(<?= $this->e((string) $total) ?>)</span></h1>
    <a class="button" href="<?= $this->e($this->url('/admin/products/new')) ?>"><?= $this->e($this->t('product.new')) ?></a>
</div>

<form method="get" action="<?= $this->e($this->url('/admin/products')) ?>" class="filters">
    <div class="field">
        <label for="f_q"><?= $this->e($this->t('common.search')) ?></label>
        <input id="f_q" name="q" type="search" value="<?= $this->e($filters['q']) ?>" maxlength="100" placeholder="<?= $this->e($this->t('product.search_placeholder')) ?>">
    </div>
    <?= $this->partial('partials/form-select', ['name' => 'category', 'label' => $this->t('product.category'), 'value' => $filters['category'], 'options' => $categories, 'emptyLabel' => $this->t('common.all')]) ?>
    <div class="field">
        <label for="f_status"><?= $this->e($this->t('product.col.status')) ?></label>
        <select id="f_status" name="status">
            <option value=""><?= $this->e($this->t('common.all')) ?></option>
            <option value="active"<?= $filters['status'] === 'active' ? ' selected' : '' ?>><?= $this->e($this->t('common.active')) ?></option>
            <option value="inactive"<?= $filters['status'] === 'inactive' ? ' selected' : '' ?>><?= $this->e($this->t('common.inactive')) ?></option>
        </select>
    </div>
    <div class="field">
        <label for="f_sort"><?= $this->e($this->t('common.sort')) ?></label>
        <select id="f_sort" name="sort">
<?php foreach ($sorts as $sort): ?>
            <option value="<?= $this->e($sort) ?>"<?= $filters['sort'] === $sort ? ' selected' : '' ?>><?= $this->e($this->t('product.sort.' . $sort)) ?></option>
<?php endforeach; ?>
        </select>
    </div>
    <div class="form-actions"><button type="submit" class="button"><?= $this->e($this->t('common.filter')) ?></button></div>
</form>

<?php if ($items === []): ?>
    <p><?= $this->e($this->t('product.none')) ?></p>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th scope="col"><?= $this->e($this->t('product.col.image')) ?></th>
                    <th scope="col"><?= $this->e($this->t('product.col.name')) ?></th>
                    <th scope="col"><?= $this->e($this->t('product.col.category')) ?></th>
                    <th scope="col"><?= $this->e($this->t('product.col.price')) ?></th>
                    <th scope="col"><?= $this->e($this->t('product.col.stock')) ?></th>
                    <th scope="col"><?= $this->e($this->t('product.col.status')) ?></th>
                    <th scope="col"><?= $this->e($this->t('common.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
<?php foreach ($items as $item): ?>
<?php $id = (int) $item['id']; ?>
                <tr>
                    <td>
                        <?php if (!empty($item['main_image'])): ?>
                            <img class="thumb" src="<?= $this->e($this->mediaUrl((string) $item['main_image'], true)) ?>" alt="" width="48" height="48" loading="lazy">
                        <?php else: ?>
                            <span class="thumb thumb-empty" aria-hidden="true"></span>
                        <?php endif; ?>
                    </td>
                    <td><a href="<?= $this->e($this->url('/admin/products/' . $id . '/edit')) ?>"><?= $this->e((string) $item['name_fr']) ?></a><?php if (!empty($item['name_ar'])): ?> <span class="muted" lang="ar" dir="rtl">(<?= $this->e((string) $item['name_ar']) ?>)</span><?php endif; ?></td>
                    <td><?= $this->e((string) $item['category_name']) ?></td>
                    <td>
                        <?php if ($item['promo_price'] !== null): ?>
                            <s class="muted"><?= $this->e($this->money((string) $item['price'])) ?></s>
                            <strong><?= $this->e($this->money((string) $item['promo_price'])) ?></strong>
                        <?php else: ?>
                            <?= $this->e($this->money((string) $item['price'])) ?>
                        <?php endif; ?>
                        <?= $this->e($currency) ?>
                    </td>
                    <td>
                        <?php if ((int) $item['variant_count'] > 0): ?>
                            <?= $this->e($this->t('product.variants_count', ['count' => (int) $item['variant_count']])) ?>
                        <?php else: ?>
                            <?= $this->e((string) $item['stock']) ?>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge <?= (int) $item['is_active'] === 1 ? 'badge-on' : 'badge-off' ?>"><?= $this->e($this->t((int) $item['is_active'] === 1 ? 'common.active' : 'common.inactive')) ?></span></td>
                    <td class="actions">
                        <a href="<?= $this->e($this->url('/admin/products/' . $id . '/edit')) ?>"><?= $this->e($this->t('common.edit')) ?></a>
                        <form method="post" action="<?= $this->e($this->url('/admin/products/' . $id . '/toggle')) ?>" class="inline-form">
                            <?= $this->csrfField() ?>
                            <button type="submit" class="link"><?= $this->e($this->t((int) $item['is_active'] === 1 ? 'common.inactive' : 'common.active')) ?></button>
                        </form>
                        <form method="post" action="<?= $this->e($this->url('/admin/products/' . $id . '/delete')) ?>" class="inline-form" data-confirm="<?= $this->e($this->t('common.confirm_delete')) ?>">
                            <?= $this->csrfField() ?>
                            <button type="submit" class="link-danger"><?= $this->e($this->t('common.delete')) ?></button>
                        </form>
                    </td>
                </tr>
<?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= $this->partial('partials/pagination', ['page' => $page, 'pages' => $pages, 'path' => '/admin/products', 'query' => $query]) ?>
<?php endif; ?>
