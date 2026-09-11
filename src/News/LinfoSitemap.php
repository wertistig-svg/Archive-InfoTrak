<?php
namespace App\News;

final class LinfoSitemap
{
    public static function items(string $xml): array
    {
        $root = @simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET);
        if (!$root) { return []; }
        $root->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $items = [];
        foreach ($root->xpath('//s:url') ?: [] as $node) {
            $url = trim((string) $node->children('http://www.sitemaps.org/schemas/sitemap/0.9')->loc);
            $news = $node->children('http://www.google.com/schemas/sitemap-news/0.9')->news;
            $title = NewsBrief::text((string) $news->title);
            $date = strtotime((string) $news->publication_date);
            $path = parse_url($url, PHP_URL_PATH) ?: '';
            if (!ArticleEnrichment::allowed($url) || !in_array(parse_url($url, PHP_URL_HOST), ['www.linfo.re','linfo.re'], true)
                || !preg_match('~^/(la-reunion|france)/~', $path) || !$date || !$title) { continue; }
            $items[] = ['title'=>$title,'link'=>$url,'description'=>'','publisher'=>'Linfo.re','author'=>'',
                'publishedAt'=>new \DateTimeImmutable('@'.$date),'publisherZone'=>str_starts_with($path, '/la-reunion/') ? 'La Réunion' : 'France'];
        }
        usort($items, static fn ($a, $b) => $b['publishedAt'] <=> $a['publishedAt']);
        return $items;
    }

    public static function findLink(string $xml, string $title): ?string
    {
        $wanted = FeedClassifier::normalize(NewsBrief::title($title, 'Linfo.re'));
        foreach (self::items($xml) as $item) {
            if (FeedClassifier::normalize($item['title']) === $wanted) { return $item['link']; }
        }
        return null;
    }
}
