# Vérification de la partie 4

## Préparation
1. Copier l'archive par-dessus le projet (sans toucher à `.env` ni `vendor/`). Aucune migration nouvelle.
2. Dans `.env`, en local : `APP_URL=http://localhost/shop` (utilisé pour les canoniques, le plan du site et les données structurées).
3. Avoir quelques produits : catégories, produits actifs avec images (partie 3), dont un « vedette », un en promotion, un avec variantes.
4. Tests : `phpunit --testsuite unit`, `phpunit --testsuite integration` (base `shop_test`), `phpstan analyse`.

## Contrôles visuels et fonctionnels
| # | Action | Résultat attendu |
|---|---|---|
| 1 | Ouvrir `/shop/` | Bandeau d'accueil, avantages, catégories, vedettes/nouveautés/promos ; menu des catégories actives |
| 2 | Réduire la fenêtre à 360 px (mode mobile des outils du navigateur) | Mise en page fluide : recherche pleine largeur, menu défilant, grille à 1–2 colonnes, aucun défilement horizontal de la page |
| 3 | Cliquer « العربية » | Page en RTL (menu, grilles, fil d'Ariane inversés) ; noms arabes affichés quand ils existent |
| 4 | `/shop/produits` : filtrer par prix, disponibilité, promotions ; trier | Résultats corrects ; l'URL conserve les filtres ; pagination à partir de 13 produits |
| 5 | Rechercher un nom en français puis en arabe | Résultats ; tri « pertinence » proposé uniquement pendant une recherche |
| 6 | Désactiver un produit dans l'administration puis ouvrir son adresse | 404, et il disparaît de l'accueil, du catalogue et de `sitemap.xml` |
| 7 | Désactiver une catégorie | Ses produits et sa page répondent 404 ; elle disparaît du menu |
| 8 | Fiche produit : galerie (clic sur les miniatures), prix barré + pourcentage, disponibilité, options, description multiligne, similaires | Tout s'affiche ; sans JavaScript les miniatures restent visibles |
| 9 | Clavier seul : Tab, Entrée | Lien « Aller au contenu », focus visible partout, formulaires utilisables |
| 10 | Code source de la fiche : `<link rel="canonical">`, `og:*`, bloc `application/ld+json` | Présents, avec l'URL absolue de `APP_URL` |
| 11 | `/shop/sitemap.xml` et `/shop/robots.txt` | XML valide listant uniquement le contenu public ; robots interdit `/admin` |
| 12 | Nom de produit contenant `<script>alert(1)</script>` | Affiché tel quel (texte), jamais exécuté |
| 13 | Administration (`/shop/admin/...`) | Nouveau style (barre sombre, tableaux, boutons) identique à la boutique |
| 14 | Outil Lighthouse de Chrome sur l'accueil et une fiche | Accessibilité et bonnes pratiques élevées ; SEO sans avertissement bloquant |
