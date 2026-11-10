# ADR 0005 — Boutique publique, SEO et design

## Design : CSS maison au lieu de Bootstrap
Le cahier des charges parlait de Bootstrap 5. Décision : un **design system maison** (`public/assets/css/app.css`,
variables CSS, composants, grille, formulaires, tableaux) utilisé par la boutique ET l'administration.
Raisons : (1) la politique CSP (`style-src 'self'`) interdit les CDN, donc Bootstrap devrait être embarqué en local et
adapté ; (2) aucune dépendance à mettre à jour chez l'acheteur ; (3) fichier léger, propriétés CSS logiques → RTL (arabe) natif ;
(4) licence sans contrainte. Si Bootstrap est exigé plus tard, il suffira de l'ajouter dans `public/assets/vendor/` et de remplacer les classes.
Couleurs : variables `:root` ; la personnalisation par l'administrateur (logo, couleurs) arrive avec les paramètres (partie finale).

## Règle de visibilité (une seule source de vérité : `StorefrontRepository::VISIBLE`)
Un produit n'est public que s'il est actif ET sa catégorie est active ET (sous-catégorie) sa catégorie parente est active.
Un produit masqué répond **404**, comme une adresse inconnue (aucune fuite d'information). Le plan du site et les menus
appliquent la même règle.

## Disponibilité
Somme des stocks des variantes ACTIVES s'il y en a, sinon stock du produit. Calculée en SQL (même expression pour l'affichage et le filtre « disponibles »).
Les prix filtrés/triés sont les prix effectifs (promotion si elle existe). Le panier (partie 5) devra recalculer et verrouiller le stock côté serveur.

## SEO
- Adresses lisibles stables (`/produit/{slug}`, `/categorie/{slug}`), slugs validés par expression régulière dès le routeur.
- Titre, description, canonique absolue (APP_URL, sinon hôte validé de la requête), Open Graph, JSON-LD `Product` (prix effectif, devise, disponibilité).
- Pages filtrées/triées/recherche : `noindex,follow` + canonique vers la liste propre ; pagination indexable avec canonique propre.
- `sitemap.xml` (contenu visible uniquement, 50 000 entrées maximum) et `robots.txt` (interdit /admin et /langue/, annonce le plan du site).
- La langue est un cookie (pas d'URL par langue) : pas de balises `hreflang` ; l'indexation se fait dans la langue par défaut.
  Des URL par langue pourront être ajoutées si le référencement multilingue devient prioritaire.

## Sécurité
Toute donnée affichée est échappée (`View::e`, `View::text`) ; JSON-LD encodé avec `JSON_HEX_TAG|AMP|APOS|QUOT` ; toutes les entrées d'URL
passent par `ListingFilters` (liste blanche de tris, slugs/prix validés, longueurs bornées) ; recherche : caractères `%` et `_` échappés, requêtes préparées ;
aucun bouton sans action (le panier arrive en partie 5).

## Limites connues
Contenu de la page d'accueil (bannière, textes, avantages, pied de page) : textes par défaut traduits, configurables via les paramètres dans la partie finale.
