# Boutique en ligne — script e-commerce (Partie 1 : socle technique)

Version de développement `0.3.0` : socle (partie 1), authentification administrateur (partie 2), catalogue d'administration (partie 3).
Le catalogue, le panier, la commande et l'export CSV arrivent dans les parties suivantes.

## Prérequis
- PHP 8.1 ou plus (8.3 recommandé) avec `pdo_mysql`, `mbstring`, `json`
- MySQL 8 ou MariaDB 10.4+
- Apache avec `mod_rewrite` (XAMPP convient)
- Composer **uniquement pour les tests** (le site fonctionne sans `vendor/`)

## Installation locale (XAMPP, Windows)
1. Copier le dossier du projet dans `C:\xampp\htdocs\shop`.
2. Dans phpMyAdmin, créer une base vide `shop` (interclassement `utf8mb4_unicode_ci`).
3. Copier `.env.example` en `.env` puis renseigner `DB_NAME`, `DB_USER`, `DB_PASS`
   (en local : `APP_ENV=local`, `APP_DEBUG=true`).
4. Créer les tables : dans un terminal, `cd C:\xampp\htdocs\shop` puis `C:\xampp\php\php.exe bin\migrate.php`.
5. Ouvrir `http://localhost/shop/`.

5. Créer le premier administrateur : `C:\xampp\php\php.exe bin\create-admin.php admin`
   (un mot de passe temporaire est affiché une seule fois ; il faudra le changer à la première connexion).
6. Se connecter sur `http://localhost/shop/admin/login`. En cas de blocage : `php bin\unlock-admin.php admin`.

> L'assistant d'installation web (verrouillage après installation) est prévu dans la partie finale.

## Catalogue (partie 3)
- Extensions PHP requises : `gd`, `fileinfo`, `mbstring` (activées par défaut dans XAMPP ; vérifier avec `php -m`).
- Les images sont écrites dans `public/media/products/` : ce dossier doit être accessible en écriture par le serveur web.
- Administration : `/admin/categories` et `/admin/products` (après connexion).

## Tests
```
composer install
vendor/bin/phpunit --testsuite unit
vendor/bin/phpunit --testsuite integration   # RateLimiter : exige TEST_DB_NAME (base de test dédiée)
vendor/bin/phpstan analyse
```

## Structure
Voir `docs/adr/0001-architecture.md`. Seul `public/` doit être exposé ; le `.htaccess` racine
redirige vers `public/` pour les hébergements sans choix du dossier racine.

## Limites connues
- Bootstrap 5 n'est pas encore embarqué (la CSP interdit les CDN ; les fichiers iront dans `public/assets/vendor/`).
- Pages d'erreur sans mise en forme (la CSP interdit les styles en ligne).
- Pas de double authentification ni de récupération de mot de passe par e-mail.
- Le tableau de bord est un espace réservé (partie 7).
