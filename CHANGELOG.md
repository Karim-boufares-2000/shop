# Historique des modifications

## 0.4.0 — en cours (Partie 4 : boutique publique et nouveau design)
- Pages publiques : accueil (bandeau, avantages, catégories, vedettes, nouveautés, promotions), catalogue (recherche, filtres catégorie/prix/disponibilité/promotions, tri, pagination), pages de catégorie, fiches produit (galerie, promotion, variantes, similaires).
- Règle de visibilité centralisée (produit + catégorie + parent actifs) ; produits masqués = 404 ; disponibilité calculée avec les variantes actives.
- SEO : slugs, titres, descriptions, canoniques absolues, Open Graph, JSON-LD Product, sitemap.xml, robots.txt, noindex sur les pages filtrées.
- Nouveau design system (CSS maison, responsive, RTL natif, accessible) appliqué à la boutique ET à l'administration.
- Outils : `ListingFilters`, `UrlGenerator::absolute`, `View::localized/text/share`, `Request::host`, `Kernel::boot($basePath, $envOverrides)`.

## 0.3.0 — en cours (Partie 3 : catalogue d'administration)
- Catégories (2 niveaux), produits (prix/promo en DECIMAL, stock, activation, vedette), variantes (SKU, prix spécifique, stock).
- Téléversement d'images sécurisé : type réel, dimensions, ré-encodage JPEG, noms aléatoires, miniatures, aucune exécution possible.
- Liste des produits : recherche, filtres, tri en liste blanche, pagination. Journal d'audit des modifications.
- Outils : `Money` (montants exacts), `Slugger`, règle de validation `money`. Migration 003.

## 0.2.0 — en cours (Partie 2 : authentification administrateur)
- Connexion administrateur : Argon2id/bcrypt, réponses uniformes, limitation par IP et par utilisateur, verrouillage temporaire, session régénérée.
- Création du premier administrateur en ligne de commande (mot de passe temporaire, changement obligatoire) ; déverrouillage en ligne de commande.
- Changement de mot de passe (politique 12 à 72 octets) ; invalidation des autres sessions.
- Journal d'audit en base (IP en HMAC). Migration 002.
- Corrections de la partie 1 : session paresseuse (aucun cookie pour les anonymes), `Cache-Control` par défaut, dates en UTC,
  changement de langue par cookie (sans formulaire), PHP 8.5 (constante PDO dépréciée), `parse_url` remplacé dans `Request`.

## 0.1.0 — en cours (Partie 1 : socle)
- Noyau MVC : routeur, requête/réponse, conteneur, vues, configuration `.env`.
- Sécurité : sessions durcies, CSRF, en-têtes HTTP, limitation de fréquence (base de données), journal sans données personnelles.
- Base de données : PDO (requêtes préparées natives), transactions, migrations vérifiées par somme de contrôle.
- i18n français/arabe (RTL), validation serveur avec normalisation des numéros de téléphone.
- Tests unitaires et d'intégration (non exécutés à ce stade, voir docs/verification-part1.md).
