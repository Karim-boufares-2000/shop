<?php
/** @var \App\Core\View $this */
/** @var list<array<string,mixed>> $tree */
?>
<div class="page-head">
    <h1><?= $this->e($this->t('category.title')) ?></h1>
    <a class="button" href="<?= $this->e($this->url('/admin/categories/new')) ?>"><?= $this->e($this->t('category.new')) ?></a>
</div>
<?php if ($tree === []): ?>
    <p><?= $this->e($this->t('category.none')) ?></p>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th scope="col"><?= $this->e($this->t('product.col.name')) ?></th>
                    <th scope="col"><?= $this->e($this->t('category.slug')) ?></th>
                    <th scope="col"><?= $this->e($this->t('category.products_count')) ?></th>
                    <th scope="col"><?= $this->e($this->t('product.col.status')) ?></th>
                    <th scope="col"><?= $this->e($this->t('category.sort_order')) ?></th>
                    <th scope="col"><?= $this->e($this->t('common.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
<?php foreach ($tree as $top): ?>
                <?= $this->partial('admin/categories/row', ['category' => $top, 'child' => false]) ?>
<?php foreach ($top['children'] as $child): ?>
                <?= $this->partial('admin/categories/row', ['category' => $child, 'child' => true]) ?>
<?php endforeach; ?>
<?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
