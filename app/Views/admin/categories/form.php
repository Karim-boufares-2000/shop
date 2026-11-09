<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed>|null $category */
/** @var array<string,string> $values */
/** @var array<string,string> $errors */
/** @var list<array{id:int,label:string}> $parents */
$editing = $category !== null;
$action = $editing ? '/admin/categories/' . (int) $category['id'] : '/admin/categories';
?>
<div class="page-head">
    <h1><?= $this->e($this->t($editing ? 'category.edit' : 'category.new')) ?></h1>
    <a href="<?= $this->e($this->url('/admin/categories')) ?>"><?= $this->e($this->t('common.back')) ?></a>
</div>
<?php if ($errors !== []): ?>
    <p class="alert" role="alert"><?= $this->e($this->t('common.form_errors')) ?></p>
<?php endif; ?>
<form method="post" action="<?= $this->e($this->url($action)) ?>" class="form-grid">
    <?= $this->csrfField() ?>
    <?= $this->partial('partials/form-field', ['name' => 'name_fr', 'label' => $this->t('category.name_fr'), 'value' => $values['name_fr'], 'error' => $errors['name_fr'] ?? null, 'attrs' => ['maxlength' => '120', 'required' => 'required']]) ?>
    <?= $this->partial('partials/form-field', ['name' => 'name_ar', 'label' => $this->t('category.name_ar'), 'value' => $values['name_ar'], 'error' => $errors['name_ar'] ?? null, 'attrs' => ['maxlength' => '120', 'dir' => 'rtl', 'lang' => 'ar']]) ?>
    <?= $this->partial('partials/form-select', ['name' => 'parent_id', 'label' => $this->t('category.parent'), 'value' => $values['parent_id'], 'options' => $parents, 'error' => $errors['parent_id'] ?? null, 'emptyLabel' => $this->t('category.parent_none')]) ?>
    <?= $this->partial('partials/form-field', ['name' => 'sort_order', 'label' => $this->t('category.sort_order'), 'value' => $values['sort_order'], 'type' => 'number', 'error' => $errors['sort_order'] ?? null, 'attrs' => ['min' => '0', 'max' => '100000']]) ?>
    <?= $this->partial('partials/form-checkbox', ['name' => 'is_active', 'label' => $this->t('category.active'), 'checked' => $values['is_active'] === '1']) ?>
    <div class="form-actions">
        <button type="submit" class="button"><?= $this->e($this->t('common.save')) ?></button>
    </div>
</form>
