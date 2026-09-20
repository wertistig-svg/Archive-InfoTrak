# Flux directs : contrôle local du 15 septembre 2026

Validation : 108 tests, 442 assertions réussis (configuration OpenSSL locale fournie au processus de test). Les tests couvrent notamment le maintien du résumé RSS lorsque la page répond 403, le rejet d’un autre article et le contrôle des redirections.

Les 63 noms de sources ayant des articles sans résumé ont été examinés. Ce contrôle cherche les flux annoncés dans leurs pages publiques et valide leur réponse XML. « Aucun flux validé » ne signifie pas qu’aucun flux ou article détaillé n’existe.

InfoTrak consulte les flux configurés avant la page de l’article. Seul un titre correspondant (ou son ancien titre complet conservé dans l’adresse) peut compléter un article existant. Les flux nationaux ou étrangers ne sont pas importés globalement : les filtres Réunion/France restent en place. Les résumés disponibles sont conservés si la page refuse l’accès. Les redirections HTTPS sont suivies sur le même domaine, avec une limite de trois.

Les flux couvrent surtout les publications récentes. Ils ne permettent pas de retrouver automatiquement toutes les anciennes actualités issues de Google. Les pages publiques restent utilisées en complément lorsque leur adresse peut être identifiée. Aucun abonnement, compte ou API payante n’a été activé.

| Source | Flux utilisés pour compléter les articles | Résultat du contrôle de la page |
|---|---|---|
| Linfo.re | Aucun flux activé | aucun flux RSS valide annonce dans la page |
| Pévèle-Carembault | Aucun flux activé | aucun flux RSS valide annonce dans la page |
| Gendarmerie nationale | Aucun flux activé | HTTP Error 403: Forbidden |
| Police nationale | Aucun flux activé | HTTP Error 403: Forbidden |
| ANSSI | Aucun flux activé | aucun flux RSS valide annonce dans la page |
| Le Quotidien de La Réunion | Aucun flux activé | aucun flux RSS valide annonce dans la page |
| Les Numériques | Aucun flux activé | aucun flux RSS valide annonce dans la page |
| Outre-mer La 1ère | [https://la1ere.franceinfo.fr/actu/rss](https://la1ere.franceinfo.fr/actu/rss) | aucun flux RSS valide annonce dans la page |
| Imaz Press | Aucun flux activé | Flux non retenu ; récupération depuis les pages publiques déjà en place |
| France TV | Aucun flux activé | aucun flux RSS valide annonce dans la page |
| Yahoo Actualités | Aucun flux activé | aucun flux RSS valide annonce dans la page |
| purepeople.com | Aucun flux activé | aucun flux RSS valide annonce dans la page |
| Zinfos974 | [https://www.zinfos974.com/general-rss/](https://www.zinfos974.com/general-rss/) | flux RSS valide |
| Equidia | Aucun flux activé | aucun flux RSS valide annonce dans la page |
| La Libre.be | Aucun flux activé | aucun flux RSS valide annonce dans la page |
| RFI | [https://www.rfi.fr/fr/rss](https://www.rfi.fr/fr/rss) | flux RSS valide |
| Kyndryl | Aucun flux activé | aucun flux RSS valide annonce dans la page |
| franceinfo | Aucun flux activé | aucun flux RSS valide annonce dans la page |
| BFM | Aucun flux activé | aucun flux RSS valide annonce dans la page |
| Le Monde.fr | [https://www.lemonde.fr/rss/une.xml](https://www.lemonde.fr/rss/une.xml) | flux RSS valide |
| Presse Agence | Aucun flux activé | 'NoneType' object has no attribute 'lower' |
| ladepeche.fr | [https://www.ladepeche.fr/rss.xml](https://www.ladepeche.fr/rss.xml) | flux RSS valide |
| Challenges | [https://www.challenges.fr/rss.xml](https://www.challenges.fr/rss.xml) | flux RSS valide |
| Région Réunion | Aucun flux activé | HTTP Error 403: Forbidden |
| Saharamedias Fr | [https://fr.saharamedias.net/feed/](https://fr.saharamedias.net/feed/) | flux RSS valide |
| 20 Minutes | [https://www.20minutes.fr/feeds/rss-une.xml](https://www.20minutes.fr/feeds/rss-une.xml) | flux RSS valide |
| France 24 | Aucun flux activé | flux RSS valide |
| jeuxvideo.com | Aucun flux activé | aucun flux RSS valide annonce dans la page |
| Radio France | Aucun flux activé | aucun flux RSS valide annonce dans la page |
| Ouest-France | Aucun flux activé | HTTP Error 403: Forbidden |
| ski-nordique.net | Aucun flux activé | flux RSS valide |
| Generation Voyage | Aucun flux activé | aucun flux RSS valide annonce dans la page |
| L'Équipe | Aucun flux activé | aucun flux RSS valide annonce dans la page |
| L'Indépendant | [https://www.lindependant.fr/rss.xml](https://www.lindependant.fr/rss.xml) | flux RSS valide |
| TV Magazine | Aucun flux activé | aucun flux RSS valide annonce dans la page |
| Nice-Matin | Aucun flux activé | aucun flux RSS valide annonce dans la page |
| LCP-Assemblée nationale | Aucun flux activé | aucun flux RSS valide annonce dans la page |
| Radio Val d'Isère | Aucun flux activé | aucun flux RSS valide annonce dans la page |
| DCmag | [https://dcmag.fr/feed/](https://dcmag.fr/feed/) | flux RSS valide |
| cnews.fr | Aucun flux activé | flux RSS valide |
| megazap.fr | [https://www.megazap.fr/xml/syndication.rss](https://www.megazap.fr/xml/syndication.rss) | flux RSS valide |
| lenouveleconomiste.fr | [https://www.lenouveleconomiste.fr/feed/](https://www.lenouveleconomiste.fr/feed/) | flux RSS valide |
| Le Figaro | Aucun flux activé | aucun flux RSS valide annonce dans la page |
| TV-Programme.com | [https://tv-programme.com/feed](https://tv-programme.com/feed)<br>[https://tv-programme.com/feed-replays](https://tv-programme.com/feed-replays)<br>[https://tv-programme.com/feed-audiences](https://tv-programme.com/feed-audiences)<br>[https://tv-programme.com/feed-etudes](https://tv-programme.com/feed-etudes) | flux RSS valide |
| lasemaine.fr | Aucun flux activé | aucun flux RSS valide annonce dans la page |
| Siècle Digital | [https://siecledigital.fr/feed/](https://siecledigital.fr/feed/) | flux RSS valide |
| Clubic | [https://www.clubic.com/feed/rss](https://www.clubic.com/feed/rss) | flux RSS valide |
| EmarketerZ | [https://www.emarketerz.fr/feed/](https://www.emarketerz.fr/feed/) | flux RSS valide |
| vantbefinfo.com | [https://vantbefinfo.com/feed/](https://vantbefinfo.com/feed/) | flux RSS valide |
| Sortir à Paris | [https://www.sortiraparis.com/rss/sortir](https://www.sortiraparis.com/rss/sortir) | flux RSS valide |
| Foot Amateur | Aucun flux activé | flux RSS valide |
| mesinfos | Aucun flux activé | HTTP Error 403: Forbidden |
| 1jour1actu.com | Aucun flux activé | HTTP Error 403: Forbidden |
| Réunion La 1ère | [https://la1ere.franceinfo.fr/actu/rss](https://la1ere.franceinfo.fr/actu/rss) | aucun flux RSS valide annonce dans la page |
| Le Quotidien | Aucun flux activé | aucun flux RSS valide annonce dans la page |
| Actu.fr | [https://actu.fr/rss.xml](https://actu.fr/rss.xml) | flux RSS valide |
| BFMTV | Aucun flux activé | aucun flux RSS valide annonce dans la page |
| TF1 Info | [https://www.tf1info.fr/feeds/rss-une.xml](https://www.tf1info.fr/feeds/rss-une.xml) | flux RSS valide |
| Le Parisien | Aucun flux activé | HTTP Error 403: Forbidden |
| ARS La Réunion | Aucun flux activé | aucun flux RSS valide annonce dans la page |
| LeMagIT | Aucun flux activé | aucun flux RSS valide annonce dans la page |
| Banque de France | Aucun flux activé | aucun flux RSS valide annonce dans la page |
| Les Echos | Aucun flux activé | HTTP Error 403: Forbidden |

24 configurations pour 21 noms de sources (dont les deux noms de La 1ère).

Conditions spécifiques CNEWS : https://www.cnews.fr/les-flux-rss-de-cnewsfr. Le flux Imaz Press /feed/ n’a pas été activé ; ses pages publiques restent utilisées.

Les compteurs d’articles modifiés comprennent aussi les dates, images et liens : ils ne représentent pas le nombre de nouveaux résumés. Voir source-details-status.md pour les résumés encore manquants après le traitement.

Après le traitement local : 154 résumés manquants avant, 149 après. Nouveaux résumés par source : DCmag (1), Le Monde.fr (1), Outre-mer La 1ère (2), lenouveleconomiste.fr (1).
