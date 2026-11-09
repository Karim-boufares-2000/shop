# ADR 0001 — MVC personnalisé léger plutôt que Laravel

**Contexte** : produit vendu ~50 USD, installé par des acheteurs non développeurs, souvent sur hébergement mutualisé
(sans SSH, sans choix du dossier racine).

**Décision** : MVC maison en PHP ≥ 8.1, sans dépendance d'exécution, autoloader PSR-4 intégré, `vendor/` non requis.

**Conséquences**
- (+) Installation par simple copie de fichiers, surface de code auditable, aucune dépendance à mettre à jour côté acheteur.
- (−) Routeur, validation, CSRF et sessions sont du code à maintenir et à tester nous-mêmes.

**Règles d'architecture**
- Controllers : lire la requête, appeler un Service, retourner une `Response`. Pas de SQL, pas de logique métier.
- Services : logique métier (prix, frais, stock, workflow). Repositories : SQL uniquement, requêtes préparées.
- Conteneur explicite : toute dépendance est déclarée dans `Kernel::registerServices()`.
- Sécurité par défaut : CSRF global sur tout POST, en-têtes de sécurité sur toute réponse (erreurs comprises), refus par défaut (404/405).
- Configuration : `.env` (secrets) + `app/Config/*.php` ; réglages boutique en base (table `settings`).
- Migrations : fichiers SQL immuables, vérifiés par SHA-256, normalisés CRLF/LF.
