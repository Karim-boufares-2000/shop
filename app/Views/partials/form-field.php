<?php
/** @var \App\Core\View $this */
/** @var string $name */
/** @var string $label */
/** @var string $value */
/** @var string|null $error */
/** @var string|null $type  text|textarea|number... */
/** @var array<string,string>|null $attrs  attributs HTML fournis par le code (jamais par l'utilisateur) */
/** @var string|null $hint */
$type = $type ?? 'text';
$attrs = $attrs ?? [];
$error = $error ?? null;
$hint = $hint ?? null;
$id = 'f_' . $name;
$attrHtml = '';
foreach ($attrs as $attrName => $attrValue) {
    $attrHtml .= ' ' . $attrName . '="' . $this->e($attrValue) . '"';
}
$describedBy = ($error !== null ? ' aria-invalid="true" aria-describedby="' . $this->e($id) . '_err"' : '');
?>
<div class="field">
    <label for="<?= $this->e($id) ?>"><?= $this->e($label) ?></label>
<?php if ($type === 'textarea'): ?>
    <textarea id="<?= $this->e($id) ?>" name="<?= $this->e($name) ?>" rows="5"<?= $attrHtml ?><?= $describedBy ?>><?= $this->e($value) ?></textarea>
<?php else: ?>
    <input id="<?= $this->e($id) ?>" name="<?= $this->e($name) ?>" type="<?= $this->e($type) ?>" value="<?= $this->e($value) ?>"<?= $attrHtml ?><?= $describedBy ?>>
<?php endif; ?>
<?php if ($hint !== null): ?>
    <small class="hint"><?= $this->e($hint) ?></small>
<?php endif; ?>
<?php if ($error !== null): ?>
    <p class="field-error" id="<?= $this->e($id) ?>_err" role="alert"><?= $this->e($error) ?></p>
<?php endif; ?>
</div>
