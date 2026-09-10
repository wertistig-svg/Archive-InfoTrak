# InfoTrak.re — bêta publique

Site de test : https://infotrak-re.onrender.com/

### Actualités et notifications sur téléphone (10 septembre 2026)

Le conteneur lance une collecte RSS à son démarrage puis toutes les 15 minutes tant qu’il est actif. Les imports sont dédupliqués ; un verrou PostgreSQL empêche deux collectes simultanées. Une source indisponible ne bloque pas les autres. Sur l’offre gratuite Render, la mise en veille interrompt cette boucle : un hébergement toujours actif est nécessaire pour des alertes continues.

La page `/notifications-telephone` permet une activation volontaire, un essai et une désactivation sur chaque appareil. Web Push utilise une clé VAPID persistante, chiffrée dans PostgreSQL avec `APP_SECRET` (conserver ce secret lors des redéploiements). Les destinations sont limitées aux services push des navigateurs, les écritures sont protégées par CSRF et les abonnements isolés par compte ou session. `app:push:dispatch` regroupe les nouveaux articles correspondant aux préférences, hors démonstrations et articles masqués, au maximum une fois par heure et par appareil. Les abonnements expirés sont supprimés. Les publications de plus de 24 heures et les articles déjà présents à l’activation ne sont pas envoyés. Les erreurs temporaires sont réessayées au passage suivant.

Sur iPhone/iPad (iOS 16.4+), ajouter le site à l’écran d’accueil et l’ouvrir depuis cette icône avant l’activation. Sur Android, utiliser un navigateur compatible comme Chrome. La réception sur un vrai téléphone doit être vérifiée avec « Envoyer un essai » ; une réponse positive du fournisseur push ne garantit pas l’affichage si les réglages du téléphone le bloquent.

La connexion Google utilise `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` et l’URI exacte `https://infotrak-re.onrender.com/connect/google/check` autorisée dans Google Cloud. Les en-têtes HTTPS du proxy privé sont pris en compte. Aucun secret n’est versionné.

Application PHP/Symfony pour consulter les actualités de La Réunion et d’ailleurs, retrouver leurs sources et personnaliser son fil. Cette version reprend le projet existant et prépare les essais avant publication.

Projet open source distribué sous [licence MIT](LICENSE).

## Démarrer

Environnement utilisé pour les vérifications : PHP 8.5.10, Symfony 8.1, PostgreSQL et Composer. Activer notamment `pdo_pgsql`, `intl`, `mbstring`, `dom` et `xml`. Les dépendances exactes sont dans `composer.lock` ; PHP 8.5 est nécessaire pour la version installée de PHPUnit.

Sur une nouvelle installation :

```powershell
composer install
```

Configurer `DATABASE_URL` et `APP_SECRET` dans `.env.local`, avec une base PostgreSQL dédiée. Pour Google, renseigner `GOOGLE_CLIENT_ID` et `GOOGLE_CLIENT_SECRET` et enregistrer l’URI de retour `/connect/google/check` pour l’adresse utilisée. La consultation du site fonctionne sans compte Google.

```powershell
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction
symfony serve --port=8000
```

Ouvrir [InfoTrak en local](http://127.0.0.1:8000/). Sans la CLI Symfony, utiliser `php -S 127.0.0.1:8000 -t public`. Ces serveurs sont destinés au développement. Pour Google, conserver exactement `http://127.0.0.1:8000/connect/google/check` parmi les URI autorisées. Le déploiement public utilise Render.

L’interface utilise une police sans empattements (Arial / Helvetica / système), proche de la lecture sur X, sans téléchargement de police externe. Les anciennes polices ne sont plus chargées.

Agent Reach est disponible pour les essais locaux de collecte : voir [installation et résultats](docs/AGENT_REACH.md). Les publications sociales destinées au site passent par les API officielles et la commande contrôlée ci-dessous.

## Données

Le fil utilise les articles déjà présents dans la base. Il ne lance pas un import à chaque affichage. La date de dernier ajout et la mention de collecte régulière permettent de connaître son état.

```powershell
# Simuler un import, puis importer un flux configuré.
php bin/console app:news:import --feed=zinfos974 --limit=10 --dry-run
php bin/console app:news:import --feed=zinfos974 --limit=10

# Réseaux sociaux : simuler, puis collecter un réseau configuré.
php bin/console app:social:import x --topic=gaming --topic=cybersécurité --dry-run
php bin/console app:social:import x --topic=gaming --topic=cybersécurité

# Ajouter les exemples pour essayer une base vide.
php bin/console app:seed
```

Les exemples sont identifiés par `is_demo`, masqués par défaut et activables avec « Afficher les exemples de test » ou `/?demo=1`. Ils ne génèrent pas d’alertes. Le seeder met à jour les exemples existants : il ne doit pas servir à importer de vraies nouvelles.

L’import existant comprend des flux de presse, Google News, Reddit et Mastodon. Leur disponibilité dépend des éditeurs. La recherche web existante interroge plusieurs fournisseurs et conserve ses résultats pendant 15 minutes. Elle est séparée de la recherche dans les articles collectés.

## Fonctions disponibles

- Lecture, recherche, choix des sujets et zones sans connexion ; préférences et refus liés à la session du visiteur.
- Connexion Google existante ; préférences liées au compte pour les personnes connectées.
- Filtres cumulables et pagination sur l’ensemble de la base, avec 12 articles par page.
- Intérêt enregistré et masquage persistant ; aucune réintroduction automatique quand le fil est vide.
- Notifications dans l’application, privées par compte, préparées à l’ouverture du panneau et à l’enregistrement des préférences. L’envoi sur téléphone est disponible via une activation séparée ; aucun envoi par e-mail.
- Lecture des commentaires publique ; publication et suppression réservées aux comptes connectés.
- Lecteurs vidéo existants conservés. Aucun exemple vidéo sans rapport n’a été ajouté.
- Interface adaptée au mobile, thèmes clair et sombre, menu et fenêtre de préférences accessibles au clavier.
- 16 sujets sélectionnables et enregistrables dans les préférences. Les catégories « Sport », « Cinéma & séries », « Musique » et « Streaming & créateurs » sont distinctes de « Jeux ».
- Tableau de bord avec articles réels, sources représentées, publications des sept derniers jours et alertes privées. Les exemples sont exclus des compteurs et répartitions.
- [Réseaux & communautés](http://127.0.0.1:8000/communautes) : sélection multiple de X/Twitter, Instagram, Facebook, Reddit, Threads et TikTok, communautés proposées, suggestion personnelle selon les sujets suivis, recherche combinée et pagination. Les choix sont conservés dans l’URL, indépendamment des préférences du fil.

La page communautés affiche les messages sociaux présents en base, datés des sept derniers jours, hors exemples et articles masqués. Le classement compte une mention par sujet ou hashtag et par message, avec un minimum de deux messages ; il ne représente pas les tendances officielles des plateformes. Analyse bornée aux 1 000 derniers messages sociaux. L'état de chaque connecteur, le nombre de messages et le nombre de sources sont maintenant visibles sur la page.

Renseigner les accès accordés par les plateformes uniquement dans `.env.local` : `X_BEARER_TOKEN`, `THREADS_ACCESS_TOKEN`, `INSTAGRAM_ACCESS_TOKEN` avec `INSTAGRAM_USER_ID`, `FACEBOOK_ACCESS_TOKEN` avec une liste `FACEBOOK_PAGE_IDS`, et `TIKTOK_RESEARCH_TOKEN`. Le code ne récupère aucun cookie de compte. X utilise la recherche récente officielle, Threads la recherche par mot-clé, Instagram la recherche de hashtags, Facebook les pages explicitement configurées et TikTok son API Research. Les permissions et offres API de chaque plateforme restent applicables.

Le flux public Reddit r/france a permis d’importer 8 discussions lors de l’essai du 8 septembre 2026 (heure de La Réunion). Il se relance manuellement :

```powershell
php bin/console app:news:import --feed=reddit-france --limit=20
```

Le flux r/reunion testé ne contenait qu’une entrée de 2022 ; une recherche Reddit supplémentaire a ensuite renvoyé HTTP 429. Aucun contournement ni nouvelle tentative automatique n’est effectué. La disponibilité des flux sociaux n’est pas garantie.

Pour reclasser les anciens articles après modification des règles : `php bin/console app:news:classify` simule ; `--apply` enregistre après sauvegarde des anciennes catégories dans `var/categories-before-*.json`. Les exemples sont ignorés. Lors de cette mise à jour, 16 articles réels ont été reclassés.

Les préférences des visiteurs ne sont pas transférées automatiquement vers leur compte Google. Le stockage des préférences invitées côté serveur n’a pas encore de politique de purge.

## Sens des indications de provenance

| Indication | Ce qu’elle permet de savoir |
| --- | --- |
| Source identifiée | L’éditeur ou le flux est identifié. |
| Source retrouvée | Le contrôle a retrouvé des mots du titre dans la page source. |
| À recouper | Une vérification complémentaire est nécessaire. |
| Réseau social | Il s’agit d’un contenu issu d’un réseau social. |
| Source inaccessible | Le contrôle n’a pas pu atteindre la page. |
| Démonstration | Le contenu est un exemple de test. |

Aucun de ces indicateurs ne certifie l’exactitude des faits. Le champ historique `isVerified` est conservé pour compatibilité avec les données existantes ; les nouveaux imports et contrôles automatiques ne le positionnent plus à vrai. L’interface traduit les anciennes valeurs sans afficher une promesse de fact-checking. Les anciens scores arbitraires de fiabilité ne sont plus affichés.

## Vérifier le projet

Validation de la mise à jour du 8 septembre 2026 : 83 tests PHPUnit (356 assertions), contrôles Twig, conteneur Symfony et schéma Doctrine réussis. Contrôles visuels du fil, des communautés, des 16 sujets et du tableau de bord ; sélection de plusieurs réseaux et affichage à 390 × 844 pixels, thèmes clair et sombre. Les tests couvrent aussi les imports X et TikTok simulés, la réutilisation d’une source, le refus des publications sociales sans date et l’accès protégé à l’espace de synchronisation.

Validation du 7 septembre 2026 : 69 tests PHPUnit passent, ainsi que les contrôles Twig, conteneur Symfony, schéma Doctrine et syntaxe JavaScript. Essais navigateur réalisés sur ordinateur et à 390 × 844 px : préférences invitées, masquage après rechargement, retour article/fil, menu mobile, thèmes clair/sombre, recherche vide et graphiques. La connexion OAuth réelle à Google, les services externes en production et les appareils iOS/Android physiques n’ont pas été validés pendant cette étape.

Créer une base dédiée aux tests avant la première exécution. L’environnement `test` utilise le suffixe `_test` défini dans la configuration Doctrine ; vérifier qu’il pointe bien sur une base distincte.

```powershell
php bin/console doctrine:database:create --env=test --if-not-exists
php bin/console doctrine:migrations:migrate --env=test --no-interaction
php bin/phpunit
php bin/console lint:twig templates
php bin/console lint:container
php bin/console doctrine:schema:validate
node --check assets/infotrak.js
```

Les tests de régression couvrent notamment deux sessions distinctes, deux comptes distincts, les requêtes sans jeton CSRF valable, les payloads invalides, la combinaison recherche/zone, les caractères `%` et `_`, la pagination et la persistance des refus.

Essais manuels utiles : ouvrir un article puis revenir au fil ; utiliser ensuite le menu et les préférences ; masquer un article et recharger ; essayer une recherche sans résultat ; passer en mode clair ; tester un écran de 390 px de large. Le cache de navigation Turbo a fait l’objet d’une correction spécifique.

## Migration et sauvegarde

La migration `Version20260907103853` ajoute l’identification des démonstrations et le propriétaire des notifications. Les anciennes notifications ne possédant pas de destinataire fiable restent conservées avec une clé `legacy-*` et ne sont montrées à aucun compte. Elles ne sont pas attribuées arbitrairement au prochain utilisateur.

Une copie des sources avant reprise se trouve dans `.snapshots/codex-before-20260907.zip`. Ce fichier n’est pas une sauvegarde de PostgreSQL et n’inclut pas les secrets `.env.local`. Le dossier `.snapshots` est exclu de Git. Le dépôt GitHub alimente le déploiement Render.

## Suite

## Mise en ligne pour les essais

Le projet contient un `Dockerfile` et un Blueprint `render.yaml` prêts pour Render. Le Blueprint crée l’application Symfony et sa base PostgreSQL, applique les migrations et charge les données de démonstration au démarrage.

Pour lancer la mise en ligne, ouvrir [le tableau de bord Blueprint de Render](https://dashboard.render.com/blueprints), connecter GitHub et sélectionner ce dépôt. La consultation publique fonctionne sans connexion Google. Pour activer la connexion, ajouter `GOOGLE_CLIENT_ID` et `GOOGLE_CLIENT_SECRET` dans les variables secrètes Render, puis déclarer `https://infotrak-re.onrender.com/connect/google/check` comme URI de redirection dans Google Cloud.

Voir [la feuille de route](docs/FEUILLE_DE_ROUTE.md). Les fonctionnalités IA, les intégrations sociales supplémentaires, le résumé quotidien, la réception push sur appareils physiques et l’installation mobile restent à valider. Le code est distribué sous licence MIT ; les contributeurs doivent toutefois vérifier séparément les droits associés aux contenus agrégés.
