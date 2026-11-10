<?php
/** @var \App\Core\View $this */
/** @var list<array{label:string,path:?string}> $items */
?>
<nav aria-label="<?= $this->e($this->t('breadcrumb.label')) ?>">
    <ol class="breadcrumb">
<?php foreach ($items as $item): ?>
<?php if ($item['path'] !== null): ?>
        <li><a href="<?= $this->e($this->url($item['path'])) ?>"><?= $this->e($item['label']) ?></a></li>
<?php else: ?>
        <li aria-current="page"><?= $this->e($item['label']) ?></li>
<?php endif; ?>
<?php endforeach; ?>
    </ol>
</nav>
