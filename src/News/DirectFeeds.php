<?php
namespace App\News;

/** Public RSS endpoints verified on 2026-09-14/15. Used to enrich matching articles only. */
final class DirectFeeds
{
    public const SOURCES = [
        ['name'=>'Zinfos974', 'website'=>'https://www.zinfos974.com', 'feed'=>'https://www.zinfos974.com/general-rss/'],
        ['name'=>'RFI', 'website'=>'https://www.rfi.fr', 'feed'=>'https://www.rfi.fr/fr/rss'],
        ['name'=>'Le Monde.fr', 'website'=>'https://www.lemonde.fr', 'feed'=>'https://www.lemonde.fr/rss/une.xml'],
        ['name'=>'ladepeche.fr', 'website'=>'https://www.ladepeche.fr', 'feed'=>'https://www.ladepeche.fr/rss.xml'],
        ['name'=>'Challenges', 'website'=>'https://www.challenges.fr', 'feed'=>'https://www.challenges.fr/rss.xml'],
        ['name'=>'Saharamedias Fr', 'website'=>'https://fr.saharamedias.net/', 'feed'=>'https://fr.saharamedias.net/feed/'],
        ['name'=>'20 Minutes', 'website'=>'https://www.20minutes.fr', 'feed'=>'https://www.20minutes.fr/feeds/rss-une.xml'],
        ['name'=>'L\'Indépendant', 'website'=>'https://www.lindependant.fr', 'feed'=>'https://www.lindependant.fr/rss.xml'],
        ['name'=>'DCmag', 'website'=>'https://dcmag.fr/', 'feed'=>'https://dcmag.fr/feed/'],
        ['name'=>'megazap.fr', 'website'=>'https://www.megazap.fr', 'feed'=>'https://www.megazap.fr/xml/syndication.rss'],
        ['name'=>'lenouveleconomiste.fr', 'website'=>'https://www.lenouveleconomiste.fr', 'feed'=>'https://www.lenouveleconomiste.fr/feed/'],
        ['name'=>'TV-Programme.com', 'website'=>'https://tv-programme.com/', 'feed'=>'https://tv-programme.com/feed'],
        ['name'=>'TV-Programme.com', 'website'=>'https://tv-programme.com/', 'feed'=>'https://tv-programme.com/feed-replays'],
        ['name'=>'TV-Programme.com', 'website'=>'https://tv-programme.com/', 'feed'=>'https://tv-programme.com/feed-audiences'],
        ['name'=>'TV-Programme.com', 'website'=>'https://tv-programme.com/', 'feed'=>'https://tv-programme.com/feed-etudes'],
        ['name'=>'Siècle Digital', 'website'=>'https://siecledigital.fr', 'feed'=>'https://siecledigital.fr/feed/'],
        ['name'=>'Clubic', 'website'=>'https://www.clubic.com', 'feed'=>'https://www.clubic.com/feed/rss'],
        ['name'=>'EmarketerZ', 'website'=>'https://www.emarketerz.fr', 'feed'=>'https://www.emarketerz.fr/feed/'],
        ['name'=>'vantbefinfo.com', 'website'=>'https://vantbefinfo.com/', 'feed'=>'https://vantbefinfo.com/feed/'],
        ['name'=>'Sortir à Paris', 'website'=>'https://www.sortiraparis.com', 'feed'=>'https://www.sortiraparis.com/rss/sortir'],
        ['name'=>'Actu.fr', 'website'=>'https://actu.fr', 'feed'=>'https://actu.fr/rss.xml'],
        ['name'=>'TF1 Info', 'website'=>'https://www.tf1info.fr', 'feed'=>'https://www.tf1info.fr/feeds/rss-une.xml'],
        ['name'=>'Outre-mer La 1ère', 'website'=>'https://la1ere.franceinfo.fr/reunion', 'feed'=>'https://la1ere.franceinfo.fr/actu/rss'],
        ['name'=>'Réunion La 1ère', 'website'=>'https://la1ere.franceinfo.fr/reunion', 'feed'=>'https://la1ere.franceinfo.fr/actu/rss'],
    ];
    public static function forPublisher(string $name): array
    {
        return array_values(array_filter(self::SOURCES, static fn($s)=>trim(FeedClassifier::normalize($s['name'])) === trim(FeedClassifier::normalize($name))));
    }
    public static function matchesTitle(string $label, string $title, string $publisher, string $url): bool
    {
        $wanted = trim(FeedClassifier::normalize(NewsBrief::title($title, $publisher)));
        if ($wanted === '') { return false; }
        if (trim(FeedClassifier::normalize(NewsBrief::title($label, $publisher))) === $wanted) { return true; }
        // Some editors update a headline while preserving the original title in the URL.
        // Require the complete old slug, never a fuzzy match or a shortened prefix.
        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', $wanted), '-');
        $pathTitle = preg_replace('/-\d+(?:\.html|\.php)?$/', '', basename(parse_url($url, PHP_URL_PATH) ?? ''));
        return strlen($slug) >= 40 && $pathTitle === $slug;
    }
}
