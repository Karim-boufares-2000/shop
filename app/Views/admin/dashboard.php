<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed>|null $admin */
?>
<h1><?= $this->e($this->t('admin.dashboard.title')) ?></h1>
<p><?= $this->e($this->t('admin.dashboard.welcome', ['name' => (string) ($admin['username'] ?? '')])) ?></p>
<p><?= $this->e($this->t('admin.dashboard.placeholder')) ?></p>
<ul>
    <li><a href="<?= $this->e($this->url('/admin/categories')) ?>"><?= $this->e($this->t('admin.nav.categories')) ?></a></li>
    <li><a href="<?= $this->e($this->url('/admin/products')) ?>"><?= $this->e($this->t('admin.nav.products')) ?></a></li>
    <li><a href="<?= $this->e($this->url('/admin/password')) ?>"><?= $this->e($this->t('admin.change_password.title')) ?></a></li>
</ul>
