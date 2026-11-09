<?php
/** @var \App\Core\View $this */
/** @var int $page */
/** @var int $pages */
/** @var string $path */
/** @var array<string,scalar> $query  paramètres à conserver (hors "page") */
?>
<?php if ($pages > 1): ?>
<nav class="pagination" aria-label="Pagination">
    <?php if ($page > 1): ?>
        <a href="<?= $this->e($this->url($path, $query + ['page' => $page - 1])) ?>" rel="prev"><?= $this->e($this->t('common.prev')) ?></a>
    <?php endif; ?>
    <span><?= $this->e($this->t('common.page', ['page' => $page, 'pages' => $pages])) ?></span>
    <?php if ($page < $pages): ?>
        <a href="<?= $this->e($this->url($path, $query + ['page' => $page + 1])) ?>" rel="next"><?= $this->e($this->t('common.next')) ?></a>
    <?php endif; ?>
</nav>
<?php endif; ?>
