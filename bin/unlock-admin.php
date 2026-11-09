<?php

declare(strict_types=1);

/*
 * Déverrouille un compte administrateur bloqué après trop d'échecs. Usage : php bin/unlock-admin.php nom_utilisateur
 * (Le verrouillage expire de toute façon seul après LOGIN_LOCK_MINUTES.)
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

$basePath = dirname(__DIR__);
require $basePath . '/app/bootstrap.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Core\Logger;
use App\Repositories\AdminUserRepository;
use App\Services\AuditLogger;

Env::load($basePath . '/.env');
$config = Config::fromDirectory($basePath . '/app/Config');

$username = $argv[1] ?? '';
if ($username === '') {
    fwrite(STDERR, "Usage : php bin/unlock-admin.php nom_utilisateur\n");
    exit(1);
}

try {
    $db = new Database((array) $config->get('database', []));
    $admins = new AdminUserRepository($db);
    $admin = $admins->findByUsername($username);
    if ($admin === null) {
        fwrite(STDERR, "Utilisateur introuvable.\n");
        exit(1);
    }
    $admins->unlock((int) $admin['id']);
    (new AuditLogger($db, new Logger($basePath . '/storage/logs'), (string) $config->get('app.key', '')))
        ->log('admin.unlocked', null, 'admin', (string) $admin['id'], ['via' => 'cli']);
} catch (Throwable $e) {
    fwrite(STDERR, 'Échec : ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo "Compte déverrouillé.\n";
