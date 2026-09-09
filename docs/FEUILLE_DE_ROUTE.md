# InfoTrak.re — prochaines étapes

## 1. Stabiliser les essais

Faire essayer cette bêta à quelques personnes, sur ordinateur et téléphone. Relever les recherches sans résultat, les contenus mal classés, les doublons entre éditeurs et les sources qui ne répondent plus. La classification actuelle repose sur des mots-clés : elle peut confondre « réunion » au sens d’une rencontre et « La Réunion ». Ce point doit être mesuré avant de promettre une veille locale fiable.

Prévoir une page de suivi des imports : heure, durée, articles ajoutés, source en panne, prochaine tentative. L’interface indique actuellement une actualisation manuelle. Passer ensuite les imports et les notifications dans des tâches de fond avec limites, reprise sur erreur et déduplication.

## 2. Un vrai espace Emploi 974

L’onglet actuel rassemble des actualités sur l’emploi. Il ne constitue pas encore une liste d’offres directement synchronisée avec France Travail.

Créer un type « offre d’emploi » distinct : identifiant source, métier, commune, contrat, date de publication, date de mise à jour et lien de candidature. Étudier l’accès officiel France Travail et ses conditions avant de brancher l’import. Une offre retirée ou expirée doit disparaître des résultats actifs.

## 3. Ajouter une IA traçable

Garder Symfony comme application principale. Ajouter un service de résumé derrière une interface PHP, appelé dans une tâche de fond. Conserver pour chaque résumé : document d’origine, date, version du modèle et liens utilisés. Distinguer explicitement extrait de la source et résumé généré.

Pour les chiffres, calculer les résultats à partir des jeux de données retenus, avec période, périmètre géographique, unité et date de mise à jour. L’IA peut expliquer un graphique, mais ne doit pas inventer son jeu de données. Exemple : le nombre d’articles sur l’agriculture ne mesure pas le nombre d’agriculteurs.

Le lien fourni dans le brief, [le catalogue data.gouv.fr](https://www.data.gouv.fr/dataservices), répertorie des API publiques. Il ne s’agit pas d’une API unifiée donnant accès à X, Instagram, Facebook et Threads. Il sera utile pour rechercher des données statistiques et territoriales adaptées à La Réunion. Catalogue consulté le 7 septembre 2026.

Pour la vérification des faits, construire une démarche distincte : repérer les affirmations, chercher des sources indépendantes, afficher les éléments de preuve et accepter le résultat « impossible à conclure ». La correspondance de quelques mots ne suffit pas. Comparer les outils cités dans le brief sur des exemples réunionnais avant de choisir une dépendance.

## 4. Réseaux sociaux et téléphone

Brancher chaque plateforme à travers un adaptateur distinct, après examen de ses accès officiels, de ses quotas et des contenus autorisés. Aucun accès X, Instagram, Facebook ou Threads n’a été créé pendant cette reprise. Les flux Reddit/Mastodon déjà présents restent indépendants de ces intégrations futures.

Commencer les essais d’installation avec une PWA : manifeste, icônes, gestion de la connexion et stratégie de cache qui exclut les pages personnelles. Tester ensuite le push et la distribution sur les stores. Le site actuel est responsive, mais aucun paquet Android/iOS ni application installable n’a été livré dans cette étape.

## 5. Préparer la publication

Avant l’ouverture au public : prévoir un environnement de préproduction PHP/PostgreSQL, configurer les comptes Google avec le domaine final et tester les sauvegardes/restaurations. Choisir des limites de requêtes pour la recherche externe et les commentaires, une politique de modération, une durée de conservation des sessions invitées et la suppression des données personnelles.

Les services qui récupèrent des pages et images externes doivent recevoir une protection contre les adresses privées, les redirections non autorisées et les réponses trop volumineuses. La reprise n’est pas un audit de sécurité complet des importeurs.

Préparer les informations de confidentialité, la gestion du consentement pour les contenus tiers et les droits de reprise des extraits/images. Choisir une licence open source pour le code. N’inclure dans le dépôt public ni secrets, ni base de données, ni snapshots de travail.

La publication publique reste une étape séparée, après ces essais et validation du périmètre prêt à ouvrir.
