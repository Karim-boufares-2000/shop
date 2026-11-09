<?php
/** @var \App\Core\View $this */
/** @var string|null $error  clé de traduction */
/** @var string $username */
?>
<section class="auth-card">
    <h1><?= $this->e($this->t('admin.login.title')) ?></h1>
    <?php if (!empty($error)): ?>
        <p class="alert" role="alert"><?= $this->e($this->t($error)) ?></p>
    <?php endif; ?>
    <form method="post" action="<?= $this->e($this->url('/admin/login')) ?>">
        <?= $this->csrfField() ?>
        <label for="username"><?= $this->e($this->t('admin.username')) ?></label>
        <input id="username" name="username" type="text" value="<?= $this->e($username) ?>" autocomplete="username" maxlength="50" required autofocus>
        <label for="password"><?= $this->e($this->t('admin.password')) ?></label>
        <input id="password" name="password" type="password" autocomplete="current-password" maxlength="1000" required>
        <button type="submit"><?= $this->e($this->t('admin.login.submit')) ?></button>
    </form>
</section>
