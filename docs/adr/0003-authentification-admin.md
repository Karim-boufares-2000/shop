# ADR 0003 — Authentification administrateur

**Décisions**
- Mots de passe : Argon2id si disponible, sinon bcrypt (`password_hash`). Longueur 12 à 72 octets (limite bcrypt appliquée partout).
- Aucun compte par défaut : `bin/create-admin.php` génère un mot de passe temporaire aléatoire (affiché une fois,
  `must_change_password = 1`). La première connexion impose son remplacement.
- Réponse identique pour utilisateur inconnu / mot de passe faux / compte verrouillé ; temps de calcul équilibré (`burn`).
- Protection en couches : limite par IP (middleware, 10 tentatives / 15 min), limite par nom d'utilisateur (10 / 15 min),
  verrouillage du compte (5 échecs → 15 min). Risque résiduel : un attaquant peut verrouiller un compte connu (déni de service) ;
  atténué par la limite par IP et `bin/unlock-admin.php`.
- Session : régénérée à la connexion et au changement de mot de passe ; empreinte du hachage conservée en session → un
  changement de mot de passe invalide toutes les autres sessions. Expirations d'inactivité et absolue.
- Session paresseuse : aucune session ni cookie pour un visiteur anonyme (la langue est un cookie de préférence séparé).
- Pages `/admin` : `Cache-Control: no-store`, `X-Robots-Tag: noindex`, accès refusé par défaut (middleware sur le groupe).
- Déconnexion : POST + CSRF uniquement.
- Audit : connexions, échecs, blocages, déconnexions, changements de mot de passe. IP et noms tentés stockés en HMAC
  (clé `APP_KEY`) ; sans clé, aucune IP n'est enregistrée. Un échec d'écriture d'audit n'empêche pas l'action (journalisé en fichier).
- Dates stockées en UTC (`SET time_zone = '+00:00'` à chaque connexion).

**Non couvert (parties ultérieures)** : double authentification, rôles multiples, récupération de compte par e-mail.
