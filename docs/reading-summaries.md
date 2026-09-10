# Aperçus et lecture

Les cartes affichent jusqu’à deux phrases complètes (70 mots au maximum). La page de lecture présente jusqu’à quatre phrases (110 mots au maximum), avec attribution et lien vers l’original. Il s’agit d’extraits condensés, sans génération de faits ni reformulation automatique. Les phrases incomplètes des RSS sont écartées ; un titre seul ne devient pas un faux résumé.

L’import conserve les phrases disponibles dans la description ou `content:encoded`, et complète aussi les articles déjà connus si le flux fournit plus de texte. L’enrichissement `php bin/console app:news:enrich --limit=60` examine ensuite les dernières actualités : pages publiques Zinfos974, Linfo.re et Témoignages. Pour Linfo, un titre identique trouvé dans les pages de rubrique permet de remplacer un lien Google News par le lien original. Sans correspondance exacte, le lien n’est pas deviné.

Les requêtes sont limitées à HTTPS et aux domaines déclarés, sans redirections, authentification ni accès aux réseaux privés. Chaque réponse est limitée à 1,5 Mo et 8 secondes, avec un cache d’une heure. La commande tourne dans `docker/collect.sh` toutes les quinze minutes lorsque le service est actif. Elle n’effectue aucune lecture externe pendant le chargement d’une page utilisateur. Les sources qui ne fournissent pas assez de texte affichent un renvoi explicite à leur article.

La navigation mobile verrouille le défilement de l’arrière-plan, rend le contenu principal inerte, garde le clavier dans le tiroir et restaure le défilement à la fermeture. Le raccourci téléphone est dans le menu du compte.

## Signalement Chrome

Un avertissement Google Safe Browsing ne se corrige pas avec le CSS. Le diagnostic public a indiqué « Aucune donnée disponible » lors du contrôle du 10 septembre 2026 ; cela ne certifie pas la sécurité du site et n’invalide pas la capture utilisateur. Le rapport « Problèmes de sécurité » de Google Search Console doit être consulté par le propriétaire pour connaître les URL signalées et demander un examen après correction. Aucun avertissement n’a été contourné et aucune demande d’examen n’a été envoyée.
