<?php
/** @var int $status */
/** @var string $titleFr */
/** @var string $titleAr */
/** @var string|null $detail */
$h = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= $h((string) $status) ?> – <?= $h($titleFr) ?></title>
</head>
<body>
    <main>
        <h1><?= $h((string) $status) ?></h1>
        <p lang="fr"><?= $h($titleFr) ?></p>
        <p lang="ar" dir="rtl"><?= $h($titleAr) ?></p>
        <?php if ($detail !== null): ?>
            <pre><?= $h($detail) ?></pre>
        <?php endif; ?>
    </main>
</body>
</html>
