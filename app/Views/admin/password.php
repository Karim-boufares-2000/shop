<?php
/** @var \App\Core\View $this */
/** @var string|null $error  clé de traduction */
/** @var bool $forced */
?>
<section class="auth-card">
    <h1><?= $this->e($this->t('admin.change_password.title')) ?></h1>
    <?php if (!empty($forced)): ?>
        <p class="notice" role="status"><?= $this->e($this->t('admin.change_password.forced')) ?></p>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <p class="alert" role="alert"><?= $this->e($this->t($error)) ?></p>
    <?php endif; ?>
    <form method="post" action="<?= $this->e($this->url('/admin/password')) ?>">
        <?= $this->csrfField() ?>
        <label for="current_password"><?= $this->e($this->t('admin.current_password')) ?></label>
        <input id="current_password" name="current_password" type="password" autocomplete="current-password" maxlength="1000" required>
        <label for="new_password"><?= $this->e($this->t('admin.new_password')) ?></label>
        <input id="new_password" name="new_password" type="password" autocomplete="new-password" minlength="12" maxlength="72" required>
        <label for="new_password_confirmation"><?= $this->e($this->t('admin.confirm_password')) ?></label>
        <input id="new_password_confirmation" name="new_password_confirmation" type="password" autocomplete="new-password" minlength="12" maxlength="72" required>
        <button type="submit"><?= $this->e($this->t('admin.change_password.submit')) ?></button>
    </form>
</section>
