<?php

declare(strict_types=1);

/*
 * Crée un compte administrateur. Usage (ligne de commande uniquement) :
 *     php bin/create-admin.php [nom_utilisateur]
 *
 * Un mot de passe TEMPORAIRE aléatoire est généré et affiché UNE seule fois ; le compte est marqué
 * "doit changer son mot de passe" : la première connexion impose d'en choisir un nouveau.
 * Aucun mot de passe n'est jamais stocké en clair ni écrit dans un fichier.
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
use App\Services\PasswordHasher;
use App\Validation\PasswordPolicy;

$envFile = $basePath . '/.env';
Env::load($envFile);
$config = Config::fromDirectory($basePath . '/app/Config');

$username = $argv[1] ?? null;
if ($username === null) {
    fwrite(STDOUT, "Nom d'utilisateur (3 à 50 caractères : lettres, chiffres, . _ -) : ");
    $username = trim((string) fgets(STDIN));
}
if (preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username) !== 1) {
    fwrite(STDERR, "Nom d'utilisateur invalide.\n");
    exit(1);
}

// Clé secrète du journal d'audit : générée une seule fois si absente.
$key = (string) $config->get('app.key', '');
if ($key === '') {
    $key = bin2hex(random_bytes(32));
    $lines = is_file($envFile) ? (file($envFile, FILE_IGNORE_NEW_LINES) ?: []) : [];
    $found = false;
    foreach ($lines as $i => $line) {
        if (preg_match('/^\s*APP_KEY\s*=/', $line) === 1) {
            $lines[$i] = 'APP_KEY=' . $key;
            $found = true;
        }
    }
    if (!$found) {
        $lines[] = 'APP_KEY=' . $key;
    }
    file_put_contents($envFile, implode(PHP_EOL, $lines) . PHP_EOL, LOCK_EX);
    fwrite(STDOUT, "APP_KEY générée et enregistrée dans .env.\n");
}

try {
    $db = new Database((array) $config->get('database', []));
    $admins = new AdminUserRepository($db);
    if ($admins->findByUsername($username) !== null) {
        fwrite(STDERR, "Ce nom d'utilisateur existe déjà.\n");
        exit(1);
    }

    $alphabet = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sans caractères ambigus (0 O 1 l I)
    do {
        $password = '';
        for ($i = 0; $i < 20; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
    } while (PasswordPolicy::check($password, $username) !== null);

    $hasher = new PasswordHasher();
    $id = $admins->create($username, $hasher->hash($password), true);
    (new AuditLogger($db, new Logger($basePath . '/storage/logs'), $key))->log('admin.created', null, 'admin', (string) $id, ['via' => 'cli']);
} catch (Throwable $e) {
    fwrite(STDERR, 'Échec : ' . $e->getMessage() . PHP_EOL);
    if ($e->getPrevious() !== null) {
        fwrite(STDERR, 'Cause : ' . $e->getPrevious()->getMessage() . PHP_EOL);
    }
    exit(1);
}

fwrite(STDOUT, "\nAdministrateur créé : {$username}\n");
fwrite(STDOUT, "Mot de passe temporaire (affiché UNE SEULE FOIS, à changer dès la première connexion) :\n\n    {$password}\n\n");
