<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $category */
/** @var bool $child */
$id = (int) $category['id'];
?>
<tr>
    <td><?= $child ? '— ' : '' ?><?= $this->e((string) $category['name_fr']) ?><?php if (!empty($category['name_ar'])): ?> <span class="muted" lang="ar" dir="rtl">(<?= $this->e((string) $category['name_ar']) ?>)</span><?php endif; ?></td>
    <td><code><?= $this->e((string) $category['slug']) ?></code></td>
    <td><?= $this->e((string) $category['products_count']) ?></td>
    <td><span class="badge <?= (int) $category['is_active'] === 1 ? 'badge-on' : 'badge-off' ?>"><?= $this->e($this->t((int) $category['is_active'] === 1 ? 'common.active' : 'common.inactive')) ?></span></td>
    <td><?= $this->e((string) $category['sort_order']) ?></td>
    <td class="actions">
        <a href="<?= $this->e($this->url('/admin/categories/' . $id . '/edit')) ?>"><?= $this->e($this->t('common.edit')) ?></a>
        <form method="post" action="<?= $this->e($this->url('/admin/categories/' . $id . '/delete')) ?>" class="inline-form" data-confirm="<?= $this->e($this->t('common.confirm_delete')) ?>">
            <?= $this->csrfField() ?>
            <button type="submit" class="link-danger"><?= $this->e($this->t('common.delete')) ?></button>
        </form>
    </td>
</tr>
