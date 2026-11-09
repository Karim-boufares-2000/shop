# Vérification de la partie 2

## Préparation
1. Copier le contenu de l'archive par-dessus le projet (le fichier `.env` et `vendor/` ne sont pas dans l'archive).
2. `php bin\migrate.php` → applique `002_admin_security.sql`.
3. `php bin\create-admin.php admin` → note le mot de passe temporaire (affiché une seule fois). `APP_KEY` est ajoutée à `.env`.
4. Tests : `phpunit --testsuite unit`, `phpunit --testsuite integration` (base `shop_test`), `phpstan analyse`.

## Scénarios manuels
| # | Action | Résultat attendu |
|---|---|---|
| 1 | `curl.exe -i http://localhost/shop/` | Aucun `Set-Cookie` ; `Cache-Control: private, no-cache` |
| 2 | Ouvrir `/shop/admin` sans être connecté | Redirection vers `/shop/admin/login` |
| 3 | Se connecter avec le mot de passe temporaire | Redirection forcée vers « Changer le mot de passe » |
| 4 | Tenter d'ouvrir `/shop/admin` avant le changement | Retour vers la page de changement |
| 5 | Changer le mot de passe (moins de 12 caractères) | Erreur de politique, rien n'est modifié |
| 6 | Changer le mot de passe (valide) | Tableau de bord + message « Mot de passe modifié » |
| 7 | Se déconnecter, puis revenir en arrière dans le navigateur | `/admin` redirige vers la connexion (page non mise en cache) |
| 8 | 5 mauvais mots de passe puis le bon | Message générique identique ; compte verrouillé 15 min |
| 9 | `php bin\unlock-admin.php admin` puis connexion | Connexion réussie |
| 10 | Nom d'utilisateur inexistant | Même message et même statut que pour un mauvais mot de passe |
| 11 | Deux navigateurs connectés ; changer le mot de passe dans l'un | L'autre est déconnecté à sa prochaine requête |
| 12 | `curl.exe -i -X POST http://localhost/shop/admin/logout` | 403 (CSRF) |
| 13 | Table `audit_log` dans phpMyAdmin | Lignes `auth.login`, `auth.login_failed`, `auth.login_blocked`, `auth.password_changed`, `auth.logout`, `admin.created` ; aucune IP ni mot de passe en clair |
| 14 | Langue : cliquer « العربية » puis recharger | Interface RTL conservée (cookie `shop_locale`) |
| 15 | `/shop/langue/fr?redirect=//evil.example` | Redirection vers `/shop/`, jamais vers l'hôte externe |
