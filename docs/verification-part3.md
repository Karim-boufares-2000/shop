# Vérification de la partie 3

## Préparation
1. Copier l'archive par-dessus le projet (sans toucher à `.env` ni `vendor/`).
2. Vérifier les extensions : `php -m | Select-String "gd|fileinfo|mbstring"` doit afficher les trois.
3. `php bin\migrate.php` → applique `003_catalog.sql`.
4. `phpunit --testsuite unit`, puis (base `shop_test`) `phpunit --testsuite integration`, puis `phpstan analyse`.

## Scénarios manuels (connecté en administrateur)
| # | Action | Résultat attendu |
|---|---|---|
| 1 | Catégories → créer « Femme », puis « Robes » avec parent « Femme » | Arbre à deux niveaux ; slugs `femme`, `robes` |
| 2 | Créer une 3e catégorie dont le parent est « Robes » | Refusé (un seul niveau de sous-catégories) |
| 3 | Supprimer « Femme » | Refusé : contient des sous-catégories |
| 4 | Produits → créer un produit, prix `1 500,5`, promo `1200`, stock `10` | Enregistré ; liste : 1 500,50 barré, promo 1 200,00 |
| 5 | Prix `٢٥٠٠` (chiffres arabes) | Enregistré comme 2 500,00 |
| 6 | Prix `abc`, `-5`, `1,500`, promo ≥ prix, stock `-1` | Erreurs affichées, rien n'est enregistré |
| 7 | Dans la page du produit : téléverser une vraie photo JPEG/PNG | Miniature affichée ; première image = principale |
| 8 | Renommer un fichier texte en `photo.jpg` et le téléverser | Refusé (« Type de fichier non autorisé ») |
| 9 | Téléverser une image de plus de 5 Mo | Refusé (« trop volumineux ») |
| 10 | Ouvrir `public\media\products\` | Noms aléatoires `xxxxxxxx….jpg` + `_t.jpg`, jamais le nom d'origine |
| 11 | Y créer un fichier `test.php`, puis ouvrir `http://localhost/shop/media/products/test.php` | **403** (exécution interdite) ; supprimer ensuite ce fichier |
| 12 | Définir une autre image comme principale, supprimer la principale | Une seule principale à la fois ; l'autre est promue ; fichiers supprimés du disque |
| 13 | Ajouter des variantes (M / Noir, SKU `ROBE-M-N`), répéter le même SKU | Doublon refusé ; la liste affiche « 2 variante(s) » |
| 14 | Dans la liste : filtrer par nom, catégorie (principale = inclut ses sous-catégories), statut ; trier ; paginer (>20 produits) | Résultats cohérents ; paramètres conservés entre pages |
| 15 | Rechercher `%` ou `x' OR '1'='1` | Aucun effet spécial, aucune erreur SQL |
| 16 | Supprimer un produit | Disparaît ; ses images disparaissent de `public\media\products\` |
| 17 | Se déconnecter puis ouvrir `/shop/admin/products` | Redirection vers la connexion |
| 18 | Passer en arabe sur ces pages | Interface RTL, libellés arabes |
| 19 | Table `audit_log` | Lignes `category.created`, `product.created`, `product.images_added`, `variant.created`, `product.deleted`… |
