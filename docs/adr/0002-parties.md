# ADR 0002 — Découpage en parties de livraison

| Partie | Contenu | Dépend de |
|---|---|---|
| 1 | Socle : noyau, sécurité de base, migrations, i18n, validation, tests | n/a |
| 2 | Authentification admin, audit, installateur de base | 1 |
| 3 | Catalogue admin : catégories, produits, variantes, uploads sécurisés | 2 |
| 4 | Catalogue public : accueil, listes, filtres, fiche produit, SEO | 3 |
| 5 | Panier, PricingService, livraison par wilaya | 4 |
| 6 | Commande invitée transactionnelle, stock, suivi sécurisé | 5 |
| 7 | Admin commandes : workflow, historique, impression, tableau de bord | 6 |
| 8 | Export CSV | 7 |
| 9 | Paramètres boutique, finitions, installateur final, documentation, audit de sécurité | tous |
