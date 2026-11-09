<?php
/** @var \App\Core\View $this */
/** @var string $name */
/** @var string $label */
/** @var bool $checked */
$id = 'f_' . $name;
?>
<div class="field field-check">
    <input id="<?= $this->e($id) ?>" name="<?= $this->e($name) ?>" type="checkbox" value="1"<?= $checked ? ' checked' : '' ?>>
    <label for="<?= $this->e($id) ?>"><?= $this->e($label) ?></label>
</div>
