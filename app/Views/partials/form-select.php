<?php
/** @var \App\Core\View $this */
/** @var string $name */
/** @var string $label */
/** @var string $value */
/** @var list<array{id:int,label:string}> $options */
/** @var string|null $error */
/** @var string|null $emptyLabel  libellé de l'option vide (facultatif) */
$error = $error ?? null;
$emptyLabel = $emptyLabel ?? null;
$id = 'f_' . $name;
?>
<div class="field">
    <label for="<?= $this->e($id) ?>"><?= $this->e($label) ?></label>
    <select id="<?= $this->e($id) ?>" name="<?= $this->e($name) ?>"<?= $error !== null ? ' aria-invalid="true" aria-describedby="' . $this->e($id) . '_err"' : '' ?>>
<?php if ($emptyLabel !== null): ?>
        <option value=""><?= $this->e($emptyLabel) ?></option>
<?php endif; ?>
<?php foreach ($options as $option): ?>
        <option value="<?= $this->e((string) $option['id']) ?>"<?= (string) $option['id'] === $value ? ' selected' : '' ?>><?= $this->e($option['label']) ?></option>
<?php endforeach; ?>
    </select>
<?php if ($error !== null): ?>
    <p class="field-error" id="<?= $this->e($id) ?>_err" role="alert"><?= $this->e($error) ?></p>
<?php endif; ?>
</div>
