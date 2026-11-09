# ADR 0004 — Catalogue, montants et images

**Montants** : colonnes `DECIMAL(12,2)`, manipulés en PHP comme chaînes normalisées ("1500.50") ou en centimes entiers
(`App\Support\Money`). Aucun calcul en `float`. Saisie tolérante ("1 500,5", chiffres arabes) mais ambiguïtés refusées ("1,500").
Contraintes SQL (`CHECK`, `UNSIGNED`) en seconde barrière : le prix promo doit être inférieur au prix, le stock ne peut pas être négatif.

**Catalogue** : catégories sur 2 niveaux maximum ; slugs ASCII générés à la création depuis le nom français et **stables ensuite**
(SEO) ; noms/descriptions stockés en texte brut et échappés à l'affichage (aucun HTML accepté).
Stock : celui du produit sert uniquement s'il n'a pas de variantes ; sinon chaque variante a son stock (la règle de
disponibilité sera centralisée dans le service de stock de la partie 5/6).

**Images** (`ImageProcessor`) :
1. contrôle du code d'erreur PHP, de `is_uploaded_file`, de la taille (5 Mo) ;
2. type MIME RÉEL (`finfo`) limité à JPEG/PNG/WebP, cohérence avec `getimagesize` ;
3. dimensions (100 à 6000 px) et nombre de pixels (12 Mpx) vérifiés AVANT décodage (anti « decompression bomb ») ;
4. décodage GD puis ré-encodage en JPEG (supprime EXIF et tout contenu parasite ajouté à la fin du fichier) ;
5. noms aléatoires (32 hexadécimaux) ; l'extension et le nom d'origine ne sont jamais utilisés ;
6. stockage dans `public/media/products/` (les clients doivent pouvoir les afficher) avec un `.htaccess` qui interdit
   toute exécution de script. Les suppressions valident l'identifiant (regex) avant tout accès au disque.

**Contrôle d'accès** : toutes les routes du catalogue sont dans le groupe `/admin` (authentification obligatoire) ;
les actions sur une image ou une variante exigent le couple (produit, élément) → pas d'IDOR entre produits.
Toutes les modifications passent par POST + CSRF et sont journalisées (audit).

**Limites connues** : si un fichier dépasse `post_max_size`, PHP vide `$_POST` et l'administrateur voit « session expirée »
(la valeur par défaut de XAMPP est 40 Mo) ; GD doit être activé ; pas de réordonnancement manuel des images (principale + ordre d'ajout).
