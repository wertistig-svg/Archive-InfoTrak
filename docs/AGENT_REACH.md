# Agent Reach : essais locaux

Agent Reach 1.5.0 est installé dans `.tools/agent-reach-venv`, hors des dépendances PHP et du dépôt Git. Révision source : `da5044d26fc6adddb6554d5679c94ac22e76e428`. Aucune installation globale, extension de navigateur, récupération de cookies ou connexion de réseau social n'a été effectuée.

Ce logiciel organise des outils de collecte. Ce n'est pas une API unifiée directement branchée à Symfony. Son installation ne remplit pas la base InfoTrak et ne déclenche pas de collecte automatique.

## Utilisation depuis le dossier du projet

```powershell
.tools/agent-reach-venv/Scripts/agent-reach.exe --version
.tools/agent-reach-venv/Scripts/agent-reach.exe doctor --json
.tools/agent-reach-venv/Scripts/yt-dlp.exe --version
```

Le diagnostic complet est conservé localement dans `.tools/agent-reach-review/doctor.json`. La détection des exécutables par PATH a donné un faux négatif pour yt-dlp dans l'environnement de test ; l'appel par chemin explicite fonctionne (2026.08.19). Les statuts du diagnostic ne constituent pas tous des tests réseau.

## Essais du 7 septembre 2026

- RSS : requête HTTP réussie sur `https://www.zinfos974.com/feed/`, redirigée vers `/general-rss/`. Feedparser a lu 20 entrées ; les trois premières possèdent un titre et un lien.
- Web : `WebChannel().read('https://www.data.gouv.fr/')` a renvoyé 2 942 caractères via Jina Reader. Cela valide cette page uniquement.
- yt-dlp : exécution de la commande de version réussie ; recherche et lecture YouTube non testées.
- X, Reddit, Facebook, Instagram, LinkedIn et recherche Exa : non configurés. Les accès sociaux demandent des outils complémentaires et, selon le réseau, une session ou des identifiants.

Pour une future intégration au site, prévoir un collecteur séparé qui produit des résultats normalisés (titre, URL source, date, résumé), avec délais maximaux, dédoublonnage et contrôle des sources. Le fil public doit continuer à fonctionner si un service externe échoue. Les flux RSS sont déjà pris en charge par l'import PHP existant ; Agent Reach n'est pas nécessaire pour ces flux.

Sources consultées : [guide officiel](https://github.com/Panniantong/Agent-Reach/blob/main/docs/install.md), [code du projet](https://github.com/Panniantong/Agent-Reach/tree/da5044d26fc6adddb6554d5679c94ac22e76e428).
