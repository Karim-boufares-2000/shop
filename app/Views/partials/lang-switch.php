<?php /** @var \App\Core\View $this */ ?>
<nav class="lang-switch" aria-label="<?= $this->e($this->t('nav.language')) ?>">
<?php foreach (['fr' => 'Français', 'ar' => 'العربية'] as $code => $label): ?>
    <a href="<?= $this->e($this->url('/langue/' . $code, ['redirect' => $this->currentPath()])) ?>" lang="<?= $this->e($code) ?>"<?= $this->locale() === $code ? ' aria-current="true"' : '' ?>><?= $this->e($label) ?></a>
<?php endforeach; ?>
</nav>
