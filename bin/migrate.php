<?php

declare(strict_types=1);

// Usage (ligne de commande uniquement) : php bin/migrate.php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

$basePath = dirname(__DIR__);
require $basePath . '/app/bootstrap.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Core\Migrator;

Env::load($basePath . '/.env');
$config = Config::fromDirectory($basePath . '/app/Config');

try {
    $applied = (new Migrator(new Database((array) $config->get('database', [])), $basePath . '/database/migrations'))->run();
} catch (Throwable $e) {
    fwrite(STDERR, 'Échec : ' . $e->getMessage() . PHP_EOL);
    // Cause technique (hôte, base, utilisateur ; jamais le mot de passe) : réservée à la ligne de commande.
    if ($e->getPrevious() !== null) {
        fwrite(STDERR, 'Cause : ' . $e->getPrevious()->getMessage() . PHP_EOL);
    }
    exit(1);
}

echo $applied === [] ? 'Base déjà à jour.' . PHP_EOL : 'Migrations appliquées : ' . implode(', ', $applied) . PHP_EOL;
