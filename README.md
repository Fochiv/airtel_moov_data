# Gooddeal Data

Site mobile-first de vente de forfaits Airtel et Moov au Gabon. Le projet utilise PHP natif, PDO, SQLite pour l’aperçu local et MySQL en production. Aucun paquet PHP ni compilation frontend ne sont nécessaires. Bootstrap et les polices sont chargés depuis leurs CDN.

## Lancer l’aperçu Replit

Le workflow démarre le serveur PHP intégré sur le port 5000. Les forfaits de démonstration sont créés automatiquement dans `storage/gooddeal.sqlite`.

Pour créer le premier compte administrateur dans l’aperçu, ajoutez temporairement un secret `ADMIN_INITIAL_PASSWORD` d’au moins 12 caractères, puis ouvrez `/admin/login.php`. Le mot de passe est immédiatement haché dans la base. Retirez ensuite ce secret si vous ne souhaitez plus le conserver.

Sans identifiants sandbox, le catalogue reste consultable, mais le bouton d’achat affiche une indisponibilité de paiement et aucune commande n’est déclarée payée. La page admin demande une première configuration du mot de passe.

## Paiements BizConnectPay

Pour l’aperçu Replit, garder `BIZCONNECTPAY_ENVIRONMENT=SANDBOX` et configurer :

- `BIZCONNECTPAY_CLIENT_ID`
- `BIZCONNECTPAY_CLIENT_SECRET`
- `BIZCONNECTPAY_WEBHOOK_SECRET`

Créer une application `SANDBOX` avec `services.read`, `payments.read` et `payments.write`, et vérifier que le catalogue autorise `GA` / `XAF` / `COLLECTION` par Airtel Money et Moov Money. Les environnements disposent de caches et d’identifiants séparés. Les clés restent côté serveur.

Les paiements ne sont confirmés que par un webhook valide, signé, dédupliqué et correspondant au mode configuré. Chaque application BizConnectPay a son propre abonnement webhook. Configurez l’URL HTTPS `https://gooddeal-ga.top/webhook.php` avec les événements de paiement terminé et échoué, puis utilisez le secret de signature de la même application et du même environnement.

### Passage en LIVE

Le contrôle LIVE actuel a confirmé l’authentification, les autorisations `payments.read`, `payments.write` et `services.read`, ainsi que la disponibilité d’Airtel Money et Moov Money en Gabon/XAF. Il n’a pas créé de paiement et ne prouve donc pas qu’un débit aboutira sur un numéro réel.

Pour Hostinger, définir `BIZCONNECTPAY_ENVIRONMENT` à `LIVE` dans `config.local.php` uniquement après avoir configuré les trois secrets LIVE (`BIZCONNECTPAY_LIVE_CLIENT_ID`, `BIZCONNECTPAY_LIVE_CLIENT_SECRET`, `BIZCONNECTPAY_LIVE_WEBHOOK_SECRET`) et l’abonnement webhook de l’application LIVE. Le site refuse de traiter les paiements si le secret webhook du mode choisi manque. L’aperçu Replit reste en SANDBOX par défaut. Avant toute ouverture publique, faire un premier paiement réel contrôlé et vérifier son webhook; une collecte réelle nécessite l’accord du client et peut déplacer des fonds.

## Déploiement sur Hostinger

1. Créer une base MySQL et un utilisateur depuis le panneau Hostinger.
2. Copier le contenu du projet dans `public_html`, en conservant `.htaccess`.
3. Copier `config.local.example.php` vers `config.local.php`, garder `SANDBOX` pour les tests ou sélectionner `LIVE` pour la production, puis renseigner les paramètres MySQL et uniquement les identifiants/secret webhook de l’environnement sélectionné. Ne jamais publier ce fichier ni le transmettre par chat.
4. Choisir un nouveau mot de passe administrateur d’au moins 12 caractères, le placer temporairement dans `ADMIN_INITIAL_PASSWORD`, puis retirer cette valeur après la première initialisation. La base ne conserve que le hash PHP.
5. Pointer l’URL webhook vers `https://gooddeal-ga.top/webhook.php`.
6. Vérifier que l’hébergement dispose de PHP 8.1+ avec PDO MySQL, PDO SQLite, cURL et HTTPS. Les tables et forfaits initiaux se créent automatiquement au premier chargement.
7. Dans l’admin, vérifier le mode et les moyens de paiement retournés pour le Gabon avant d’activer les achats.

Pour utiliser SQLite en production, laisser `DB_DRIVER=sqlite` et rendre `storage/` accessible en écriture à PHP. MySQL reste recommandé pour le site publié.

## Pages

- `/` : catalogue public et achat sans compte.
- `/commandes.php` : recherche du statut des commandes avec le numéro à recharger.
- `/commande.php?ref=...` : état d’une commande.
- `/admin/login.php` : connexion de l’administration.
- `/admin/index.php` : indicateurs et historique des ventes confirmées.
- `/admin/forfaits.php` : création, modification, activation, désactivation et suppression de forfaits.
- `/admin/commandes.php` : commandes payées et action de livraison.
- `/admin/parametres.php` : état du paiement et URL du webhook.
- `/webhook.php` : réception des événements de paiement.

L’accès rapide à la page de connexion admin est caché derrière cinq clics rapides sur l’étape 3 de « Comment ça marche ». L’authentification et la limitation des tentatives restent requises.

