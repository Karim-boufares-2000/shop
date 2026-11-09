# Vérification de la partie 1

## État honnête
Le code a été écrit dans un environnement **sans PHP ni base de données**. Aucun test automatisé n'a été exécuté.
Seules des vérifications statiques ont été faites (parenthèses/accolades équilibrées, namespaces conformes aux chemins,
imports résolus, classes référencées existantes). **Un test de syntaxe PHP réel reste à faire** : `php -l` sur chaque fichier.

## À exécuter avant de passer à la partie 2
1. Syntaxe : `Get-ChildItem -Recurse app,tests,bin,public -Filter *.php | % { php -l $_.FullName }` (PowerShell)
2. `composer install` puis `vendor/bin/phpunit` : tous les tests unitaires doivent passer.
   Point à surveiller : `tests/Integration/KernelTest.php` démarre une vraie session PHP en ligne de commande ;
   un avertissement « headers already sent » est possible et demanderait d'adapter ce test (pas de désactiver la sécurité).
3. Base de test dédiée + variables `TEST_DB_*` : `vendor/bin/phpunit --testsuite integration`.
4. `vendor/bin/phpstan analyse` (niveau 6).

## Scénarios manuels (XAMPP)
| # | Action | Résultat attendu |
|---|---|---|
| 1 | `php bin/migrate.php` sur base vide | 4 tables + `migrations` créées ; second lancement : « Base déjà à jour » |
| 2 | Modifier `001_core_tables.sql` puis relancer la migration | Échec explicite (somme de contrôle) |
| 3 | Ouvrir `/shop/` | Page d'accueil FR, `dir="ltr"` |
| 4 | Cliquer « العربية » | Page en arabe, `dir="rtl"`, retour sur la même page |
| 5 | `curl -X POST http://localhost/shop/langue -d "locale=ar"` | 403 (jeton CSRF absent) |
| 6 | `curl -I http://localhost/shop/` | En-têtes CSP, X-Frame-Options, nosniff présents |
| 7 | Ouvrir `/shop/inexistant` | Page 404 bilingue sans trace ni chemin de fichier |
| 8 | Ouvrir `/shop/.env`, `/shop/app/Config/app.php`, `/shop/storage/logs/` | Aucun contenu exposé (404/403) |
| 9 | Mettre `APP_ENV=production`, provoquer une erreur (ex. mauvais mot de passe BD) | Page 500 générique ; détail uniquement dans `storage/logs` |
| 10 | POST `redirect=//evil.example` avec un jeton valide | Redirection vers `/`, jamais vers l'hôte externe |
