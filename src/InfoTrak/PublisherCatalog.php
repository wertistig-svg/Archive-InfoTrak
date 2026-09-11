<?php
namespace App\InfoTrak;

use App\News\FeedClassifier;

final class PublisherCatalog
{
    public const GROUPS = ['reunion'=>'Actualités Réunion', 'france'=>'Actualités France', 'official'=>'Informations officielles', 'cyber'=>'Cybersécurité', 'emploi'=>'Emploi', 'traffic'=>'La Circulation'];
    public const SOURCES = [
        ['group' => 'reunion', 'name' => 'Linfo.re', 'url' => 'https://www.linfo.re', 'topic' => 'La Réunion', 'zone' => 'La Réunion', 'mode' => 'sitemap', 'feed' => 'https://www.linfo.re/sitemap-news.xml'],
        ['group' => 'reunion', 'name' => 'Réunion La 1ère', 'url' => 'https://la1ere.franceinfo.fr/reunion', 'topic' => 'La Réunion', 'zone' => 'La Réunion', 'mode' => 'search', 'feed' => ''],
        ['group' => 'reunion', 'name' => 'Zinfos974', 'url' => 'https://www.zinfos974.com', 'topic' => 'La Réunion', 'zone' => 'La Réunion', 'mode' => 'existing', 'feed' => ''],
        ['group' => 'reunion', 'name' => 'Imaz Press', 'url' => 'https://imazpress.com', 'topic' => 'La Réunion', 'zone' => 'La Réunion', 'mode' => 'search', 'feed' => ''],
        ['group' => 'reunion', 'name' => 'Le Quotidien', 'url' => 'https://www.lequotidien.re', 'topic' => 'La Réunion', 'zone' => 'La Réunion', 'mode' => 'search', 'feed' => ''],
        ['group' => 'reunion', 'name' => 'Témoignages', 'url' => 'https://www.temoignages.re', 'topic' => 'La Réunion', 'zone' => 'La Réunion', 'mode' => 'existing', 'feed' => ''],
        ['group' => 'france', 'name' => 'Franceinfo', 'url' => 'https://www.franceinfo.fr', 'topic' => 'Société', 'zone' => 'France', 'mode' => 'rss', 'feed' => 'https://www.franceinfo.fr/france.rss'],
        ['group' => 'france', 'name' => 'Le Monde', 'url' => 'https://www.lemonde.fr', 'topic' => 'Société', 'zone' => 'France', 'mode' => 'rss', 'feed' => 'https://www.lemonde.fr/societe/rss_full.xml'],
        ['group' => 'france', 'name' => 'Le Figaro', 'url' => 'https://www.lefigaro.fr', 'topic' => 'Société', 'zone' => 'France', 'mode' => 'rss', 'feed' => 'https://www.lefigaro.fr/rss/figaro_actualite-france.xml'],
        ['group' => 'france', 'name' => 'BFMTV', 'url' => 'https://www.bfmtv.com', 'topic' => 'Société', 'zone' => 'France', 'mode' => 'search', 'feed' => ''],
        ['group' => 'france', 'name' => 'TF1 Info', 'url' => 'https://www.tf1info.fr', 'topic' => 'Société', 'zone' => 'France', 'mode' => 'search', 'feed' => ''],
        ['group' => 'france', 'name' => '20 Minutes', 'url' => 'https://www.20minutes.fr', 'topic' => 'Société', 'zone' => 'France', 'mode' => 'search', 'feed' => ''],
        ['group' => 'france', 'name' => 'Actu.fr', 'url' => 'https://actu.fr', 'topic' => 'Société', 'zone' => 'France', 'mode' => 'search', 'feed' => ''],
        ['group' => 'france', 'name' => 'Le Parisien', 'url' => 'https://www.leparisien.fr', 'topic' => 'Société', 'zone' => 'France', 'mode' => 'search', 'feed' => ''],
        ['group' => 'france', 'name' => 'Ouest-France', 'url' => 'https://www.ouest-france.fr', 'topic' => 'Société', 'zone' => 'France', 'mode' => 'search', 'feed' => ''],
        ['group' => 'official', 'name' => 'Préfecture de La Réunion', 'url' => 'https://www.reunion.gouv.fr', 'topic' => 'Alertes officielles', 'zone' => 'La Réunion', 'mode' => 'search', 'feed' => ''],
        ['group' => 'official', 'name' => 'Météo-France Réunion', 'url' => 'https://meteofrance.re', 'topic' => 'Alertes officielles', 'zone' => 'La Réunion', 'mode' => 'search', 'feed' => ''],
        ['group' => 'official', 'name' => 'ARS La Réunion', 'url' => 'https://www.lareunion.ars.sante.fr', 'topic' => 'Alertes officielles', 'zone' => 'La Réunion', 'mode' => 'search', 'feed' => ''],
        ['group' => 'official', 'name' => 'Gendarmerie nationale', 'url' => 'https://www.gendarmerie.interieur.gouv.fr', 'topic' => 'Alertes officielles', 'zone' => 'La Réunion', 'mode' => 'search', 'feed' => ''],
        ['group' => 'official', 'name' => 'Police nationale', 'url' => 'https://www.police-nationale.interieur.gouv.fr', 'topic' => 'Alertes officielles', 'zone' => 'La Réunion', 'mode' => 'search', 'feed' => ''],
        ['group' => 'official', 'name' => 'Sécurité civile', 'url' => 'https://www.securite-civile.interieur.gouv.fr', 'topic' => 'Alertes officielles', 'zone' => 'La Réunion', 'mode' => 'search', 'feed' => ''],
        ['group' => 'cyber', 'name' => 'ANSSI', 'url' => 'https://cyber.gouv.fr', 'topic' => 'Cybersécurité', 'zone' => 'France', 'mode' => 'search', 'feed' => ''],
        ['group' => 'cyber', 'name' => 'CERT-FR', 'url' => 'https://www.cert.ssi.gouv.fr', 'topic' => 'Cybersécurité', 'zone' => 'France', 'mode' => 'rss', 'feed' => 'https://www.cert.ssi.gouv.fr/feed/'],
        ['group' => 'cyber', 'name' => 'Cybermalveillance.gouv.fr', 'url' => 'https://www.cybermalveillance.gouv.fr', 'topic' => 'Cybersécurité', 'zone' => 'France', 'mode' => 'search', 'feed' => ''],
        ['group' => 'cyber', 'name' => 'LeMagIT', 'url' => 'https://www.lemagit.fr', 'topic' => 'Cybersécurité', 'zone' => 'France', 'mode' => 'search', 'feed' => ''],
        ['group' => 'emploi', 'name' => 'France Travail', 'url' => 'https://www.francetravail.fr', 'topic' => 'Emploi', 'zone' => 'La Réunion', 'mode' => 'portal', 'feed' => ''],
        ['group' => 'emploi', 'name' => 'Indeed', 'url' => 'https://fr.indeed.com', 'topic' => 'Emploi', 'zone' => 'La Réunion', 'mode' => 'portal', 'feed' => ''],
        ['group' => 'emploi', 'name' => 'LinkedIn', 'url' => 'https://www.linkedin.com/jobs', 'topic' => 'Emploi', 'zone' => 'La Réunion', 'mode' => 'portal', 'feed' => ''],
        ['group' => 'emploi', 'name' => 'DomTomJob', 'url' => 'https://www.domtomjob.com', 'topic' => 'Emploi', 'zone' => 'La Réunion', 'mode' => 'portal', 'feed' => ''],
        ['group' => 'traffic', 'name' => 'Info Trafic Réunion · CRGT', 'url' => 'https://www.infotrafic.re/fr', 'topic' => 'La Circulation', 'zone' => 'La Réunion', 'mode' => 'portal', 'feed' => ''],
        ['group' => 'traffic', 'name' => 'Routes départementales', 'url' => 'https://www.departement974.fr/routes', 'topic' => 'La Circulation', 'zone' => 'La Réunion', 'mode' => 'search', 'feed' => ''],
    ];

    public static function websiteFor(string $name): ?string
    {
        $key = trim(FeedClassifier::normalize($name));
        foreach (self::SOURCES as $source) {
            if (trim(FeedClassifier::normalize($source['name'])) === $key) { return $source['url']; }
        }
        return match ($key) { 'linfo re' => 'https://www.linfo.re', 'zinfos974 com' => 'https://www.zinfos974.com', 'outre mer la 1ere' => 'https://la1ere.franceinfo.fr/reunion', default => null };
    }

    public static function feeds(): array
    {
        $feeds = [];
        foreach (self::SOURCES as $s) {
            if (in_array($s['mode'], ['existing', 'portal'], true)) { continue; }
            $search = $s['mode'] === 'search';
            $site = preg_replace('~^https://~', '', $s['url']);
            $query = 'site:'.$site.' '.($s['zone'] === 'La Réunion' ? '"La Réunion"' : 'France');
            $url = $search ? 'https://news.google.com/rss/search?q='.rawurlencode($query).'&hl=fr&gl=FR&ceid=FR:fr' : $s['feed'];
            $feeds[] = ['slug'=>'publisher-'.FeedClassifier::slugify($s['name']), 'feedName'=>$s['name'], 'url'=>$url,
                'format'=>$s['mode'] === 'sitemap' ? 'sitemap' : 'rss', 'category'=>$s['topic'], 'place'=>$s['zone'],
                'sourceName'=>$s['name'], 'sourceType'=>in_array($s['group'], ['official','cyber'], true) && $s['name'] !== 'LeMagIT' ? 'official' : 'press',
                'score'=>70, 'verified'=>!$search, 'label'=>$search ? 'À recouper' : 'Source identifiée', 'perItemSource'=>false, 'maxPerPublisher'=>null];
        }
        $feeds[] = ['slug'=>'reunion-circulation', 'feedName'=>'Circulation à La Réunion',
            'url'=>'https://news.google.com/rss/search?q='.rawurlencode('(site:linfo.re/la-reunion OR site:zinfos974.com OR site:imazpress.com) (circulation OR embouteillage OR accident OR route) "Réunion"').'&hl=fr&gl=FR&ceid=FR:fr',
            'format'=>'rss','category'=>'La Circulation','place'=>'La Réunion','sourceName'=>'','sourceType'=>'press','score'=>70,'verified'=>false,'label'=>'À recouper','perItemSource'=>true,'maxPerPublisher'=>4];
        return $feeds;
    }
}
