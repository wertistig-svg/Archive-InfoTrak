<?php

namespace App\News;

use App\Entity\Article;
use App\Entity\Source;
use App\Repository\ArticleRepository;
use App\Repository\SourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Importe des actualités réelles depuis des flux vérifiés (presse, agrégateur, réseaux sociaux).
 *
 * Règles d'honnêteté : flux directs → vérifié ; agrégateur (sources variables) → « À recouper » ;
 * réseaux sociaux → « Réseau social ». Plafond par éditeur pour la diversité.
 * Les doublons (même URL ou même slug) sont ignorés : la commande est ré-exécutable sans risque.
 * La panne d'un flux n'empêche jamais les autres (rapportée dans les stats).
 */
final class NewsImportService
{
    private const ATOM_NS = 'http://www.w3.org/2005/Atom';

    /** @var array<int, array{slug: string, feedName: string, url: string, format: string, category: string, place: string, sourceName: string, sourceType: string, score: int, verified: bool, label: string, perItemSource: bool, maxPerPublisher: ?int}> */
    public const FEEDS = [
        ['slug' => 'temoignages', 'feedName' => 'Témoignages (Réunion)', 'url' => 'https://www.temoignages.re/spip.php?page=backend', 'format' => 'rss', 'category' => 'La Réunion', 'place' => 'La Réunion', 'sourceName' => 'Témoignages', 'sourceType' => 'press', 'score' => 80, 'verified' => true, 'label' => 'Vérifiée', 'perItemSource' => false, 'maxPerPublisher' => null],
        ['slug' => 'zinfos974', 'feedName' => 'Zinfos974', 'url' => 'https://www.zinfos974.com/feed', 'format' => 'rss', 'category' => 'La Réunion', 'place' => 'La Réunion', 'sourceName' => 'Zinfos974', 'sourceType' => 'press', 'score' => 82, 'verified' => true, 'label' => 'Vérifiée', 'perItemSource' => false, 'maxPerPublisher' => null],
        ['slug' => 'reddit-reunion', 'feedName' => 'Reddit r/reunion', 'url' => 'https://www.reddit.com/r/reunion/new/.rss?sort=new', 'format' => 'atom', 'category' => 'La Réunion', 'place' => 'La Réunion', 'sourceName' => 'Reddit r/reunion', 'sourceType' => 'social', 'score' => 60, 'verified' => false, 'label' => 'Réseau social', 'perItemSource' => false, 'maxPerPublisher' => null],
        ['slug' => 'reddit-france', 'feedName' => 'Reddit r/france', 'url' => 'https://www.reddit.com/r/france/new/.rss', 'format' => 'atom', 'category' => 'Société', 'place' => 'France', 'sourceName' => 'Reddit r/france', 'sourceType' => 'social', 'score' => 60, 'verified' => false, 'label' => 'Réseau social', 'perItemSource' => false, 'maxPerPublisher' => null],
        ['slug' => 'masto-lareunion', 'feedName' => 'Mastodon #lareunion', 'url' => 'https://piaille.fr/tags/lareunion.rss', 'format' => 'rss', 'category' => 'La Réunion', 'place' => 'La Réunion', 'sourceName' => 'Mastodon #lareunion', 'sourceType' => 'social', 'score' => 60, 'verified' => false, 'label' => 'Réseau social', 'perItemSource' => false, 'maxPerPublisher' => null],
        ['slug' => 'gnews-reunion', 'feedName' => 'Google News – La Réunion', 'url' => 'https://news.google.com/rss/search?q=La%20R%C3%A9union&hl=fr&gl=FR&ceid=FR:fr', 'format' => 'rss', 'category' => 'La Réunion', 'place' => 'La Réunion', 'sourceName' => '', 'sourceType' => 'press', 'score' => 70, 'verified' => false, 'label' => 'À recouper', 'perItemSource' => true, 'maxPerPublisher' => 4],
        ['slug' => 'gnews-tourisme', 'feedName' => 'Google News – Tourisme Réunion', 'url' => 'https://news.google.com/rss/search?q=tourisme%20%C3%AEle%20de%20La%20R%C3%A9union&hl=fr&gl=FR&ceid=FR:fr', 'format' => 'rss', 'category' => 'La Réunion', 'place' => 'La Réunion', 'sourceName' => '', 'sourceType' => 'press', 'score' => 70, 'verified' => false, 'label' => 'À recouper', 'perItemSource' => true, 'maxPerPublisher' => 4],
        ['slug' => 'gnews-sport', 'feedName' => 'Google News – Sport Réunion', 'url' => 'https://news.google.com/rss/search?q=sport%20La%20R%C3%A9union&hl=fr&gl=FR&ceid=FR:fr', 'format' => 'rss', 'category' => 'Jeux', 'place' => 'La Réunion', 'sourceName' => '', 'sourceType' => 'press', 'score' => 70, 'verified' => false, 'label' => 'À recouper', 'perItemSource' => true, 'maxPerPublisher' => 4],
        ['slug' => 'gnews-cyber', 'feedName' => 'Google News – Cybersécurité', 'url' => 'https://news.google.com/rss/search?q=cybers%C3%A9curit%C3%A9%20France&hl=fr&gl=FR&ceid=FR:fr', 'format' => 'rss', 'category' => 'Cybersécurité', 'place' => 'France', 'sourceName' => '', 'sourceType' => 'press', 'score' => 70, 'verified' => false, 'label' => 'À recouper', 'perItemSource' => true, 'maxPerPublisher' => 4],
        ['slug' => 'gnews-emploi', 'feedName' => 'Google News – Emploi Réunion', 'url' => 'https://news.google.com/rss/search?q=emploi%20La%20R%C3%A9union&hl=fr&gl=FR&ceid=FR:fr', 'format' => 'rss', 'category' => 'Emploi', 'place' => 'La Réunion', 'sourceName' => '', 'sourceType' => 'press', 'score' => 70, 'verified' => false, 'label' => 'À recouper', 'perItemSource' => true, 'maxPerPublisher' => 4],
        ['slug' => 'gnews-jeux', 'feedName' => 'Google News – Jeux & culture', 'url' => 'https://news.google.com/rss/search?q=jeux%20vid%C3%A9o%20France&hl=fr&gl=FR&ceid=FR:fr', 'format' => 'rss', 'category' => 'Jeux', 'place' => 'France', 'sourceName' => '', 'sourceType' => 'press', 'score' => 70, 'verified' => false, 'label' => 'À recouper', 'perItemSource' => true, 'maxPerPublisher' => 4],
        ['slug' => 'gnews-environnement', 'feedName' => 'Google News – Environnement', 'url' => 'https://news.google.com/rss/search?q=environnement%20La%20R%C3%A9union&hl=fr&gl=FR&ceid=FR:fr', 'format' => 'rss', 'category' => 'Environnement', 'place' => 'La Réunion', 'sourceName' => '', 'sourceType' => 'press', 'score' => 70, 'verified' => false, 'label' => 'À recouper', 'perItemSource' => true, 'maxPerPublisher' => 4],
    ];

    /** @param array<int, array{slug: string, feedName: string, url: string, format: string, category: string, place: string, sourceName: string, sourceType: string, score: int, verified: bool, label: string, perItemSource: bool, maxPerPublisher: ?int}> $feeds */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly HttpClientInterface $http,
        ?array $feeds = null,
        private readonly ?ArticleEnrichment $enrichment = null,
    ) {
        $this->feeds = $feeds ?? array_merge(self::FEEDS, \App\InfoTrak\PublisherCatalog::feeds());
    }

    /** @var array<int, array{slug: string, feedName: string, url: string, format: string, category: string, place: string, sourceName: string, sourceType: string, score: int, verified: bool, label: string, perItemSource: bool, maxPerPublisher: ?int}> */
    private readonly array $feeds;

    /** @var array<string, Source> sources déjà résolues pendant l'import (évite les doublons avant flush) */
    private array $sourceCache = [];

    /**
     * @return array{created: int, skipped: int, feeds: array<string, int>, errors: array<string, string>}
     */
    public function import(?string $feedSlug = null, int $limit = 10, bool $dryRun = false): array
    {
        $stats = ['created' => 0, 'skipped' => 0, 'feeds' => [], 'errors' => []];
        $this->sourceCache = [];

        foreach ($this->feeds as $feed) {
            if (null !== $feedSlug && $feed['slug'] !== $feedSlug) {
                continue;
            }
            $created = 0;
            try {
                $publisherCounts = [];
                foreach ($this->fetchItems($feed, $limit) as $item) {
                    // Plafond par éditeur : garantit la diversité des sources.
                    $cap = $feed['maxPerPublisher'] ?? null;
                    if (null !== $cap) {
                        $key = mb_strtolower($this->publisherKey($item, $feed));
                        $publisherCounts[$key] = ($publisherCounts[$key] ?? 0) + 1;
                        if ($publisherCounts[$key] > $cap) {
                            ++$stats['skipped'];
                            continue;
                        }
                    }
                    if ($this->integrate($item, $feed, $dryRun)) {
                        ++$created;
                    } else {
                        ++$stats['skipped'];
                    }
                }
            } catch (\Throwable $e) {
                // Un flux en panne (rate-limit, DNS…) n'empêche jamais les autres.
                $stats['errors'][$feed['feedName']] = mb_substr($e->getMessage(), 0, 160);
            }
            $stats['feeds'][$feed['feedName']] = $created;
            $stats['created'] += $created;
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        return $stats;
    }

    /**
     * @param array{slug: string, feedName: string, url: string, format: string, category: string, place: string, sourceName: string, sourceType: string, score: int, verified: bool, label: string, perItemSource: bool, maxPerPublisher: ?int} $feed
     * @return list<array{title: string, link: string, description: string, publisher: string, author: string, publishedAt: \DateTimeImmutable}>
     */
    private function fetchItems(array $feed, int $limit): array
    {
        $response = $this->http->request('GET', $feed['url'], [
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; InfoTrak/1.0; +https://example.com)'],
            'timeout' => 12,
            'max_duration' => 20,
        ]);
        $body = $response->getContent();
        if (($feed['format'] ?? '') === 'sitemap') {
            return array_slice(array_values(array_filter(LinfoSitemap::items($body), static fn ($i) => $i['publishedAt'] >= new \DateTimeImmutable('-14 days'))), 0, $limit);
        }
        $xml = @simplexml_load_string($body, \SimpleXMLElement::class, LIBXML_NONET);
        if (false === $xml) {
            return [];
        }

        return ('atom' === ($feed['format'] ?? 'rss')) ? $this->parseAtom($xml, $feed, $limit) : $this->parseRss($xml, $feed, $limit);
    }

    /**
     * @param array{slug: string, feedName: string, url: string, format: string, category: string, place: string, sourceName: string, sourceType: string, score: int, verified: bool, label: string, perItemSource: bool, maxPerPublisher: ?int} $feed
     * @return list<array{title: string, link: string, description: string, publisher: string, author: string, publishedAt: \DateTimeImmutable}>
     */
    private function parseRss(\SimpleXMLElement $xml, array $feed, int $limit): array
    {
        $items = [];
        $cutoff = new \DateTimeImmutable('-14 days');
        foreach ($xml->channel->item ?? [] as $node) {
            if (\count($items) >= $limit) {
                break;
            }
            $description = (string) ($node->description ?? '');
            $encoded = (string) $node->children('http://purl.org/rss/1.0/modules/content/')->encoded;
            if (mb_strlen($encoded) > mb_strlen($description)) { $description = $encoded; }
            $title = trim((string) ($node->title ?? ''));
            if ('' === $title) {
                // Certains flux sociaux (Mastodon) n'ont pas de titre : on le dérive du texte.
                $title = FeedClassifier::excerptOf($description, 90);
            }
            $link = trim((string) ($node->link ?? ''));
            if ('' === $title || '' === $link || !filter_var($link, FILTER_VALIDATE_URL)) {
                continue;
            }
            if ($feed['perItemSource']) {
                $link = self::extractDirectLink($description, $link);
            }
            // SPIP (Témoignages) publie dc:date et non pubDate.
            $dc = $node->children('http://purl.org/dc/elements/1.1/');
            $ts = strtotime((string) ($node->pubDate ?? $dc->date ?? ''));
            if (false === $ts) { continue; }
            $publishedAt = new \DateTimeImmutable('@'.$ts);
            if ($publishedAt < $cutoff) {
                continue;
            }
            $items[] = [
                'title' => $title,
                'link' => $link,
                'description' => $description,
                'publisherZone' => in_array(trim((string) $node->category), ['International', 'France'], true) ? trim((string) $node->category) : null,
                'publisher' => trim((string) ($node->source ?? '')),
                'author' => '',
                'publishedAt' => $publishedAt,
            ];
        }

        return $items;
    }

    /**
     * @param array{slug: string, feedName: string, url: string, format: string, category: string, place: string, sourceName: string, sourceType: string, score: int, verified: bool, label: string, perItemSource: bool, maxPerPublisher: ?int} $feed
     * @return list<array{title: string, link: string, description: string, publisher: string, author: string, publishedAt: \DateTimeImmutable}>
     */
    private function parseAtom(\SimpleXMLElement $xml, array $feed, int $limit): array
    {
        $xml->registerXPathNamespace('a', self::ATOM_NS);
        $nodes = $xml->xpath('//a:entry');
        if (false === $nodes) {
            return [];
        }

        $items = [];
        $cutoff = new \DateTimeImmutable('-14 days');
        foreach ($nodes as $node) {
            if (\count($items) >= $limit) {
                break;
            }
            /** @var \SimpleXMLElement $node */
            $ns = $node->children(self::ATOM_NS);
            $title = trim((string) ($ns->title ?? ''));
            $link = '';
            if (isset($ns->link)) {
                foreach ($ns->link as $linkNode) {
                    $attrs = $linkNode->attributes();
                    $rel = isset($attrs['rel']) ? (string) $attrs['rel'] : 'alternate';
                    if ('alternate' === $rel && isset($attrs['href'])) {
                        $link = trim((string) $attrs['href']);
                        break;
                    }
                }
            }
            $content = trim((string) ($ns->content ?? $ns->summary ?? ''));
            if ('' === $title) {
                $title = FeedClassifier::excerptOf($content, 90);
            }
            if ('' === $title || '' === $link || !filter_var($link, FILTER_VALIDATE_URL)) {
                continue;
            }
            $author = '';
            if (isset($ns->author->name)) {
                $author = trim((string) $ns->author->name);
            }
            $ts = strtotime(trim((string) ($ns->updated ?? $ns->published ?? '')));
            // Une entrée sociale sans date ne doit pas devenir une fausse actualité récente.
            if (false === $ts && 'social' === $feed['sourceType']) { continue; }
            $publishedAt = false !== $ts ? new \DateTimeImmutable('@'.$ts) : new \DateTimeImmutable();
            if ($publishedAt < $cutoff) {
                continue;
            }
            $items[] = [
                'title' => $title,
                'link' => $link,
                'description' => $content,
                'publisher' => $feed['sourceName'],
                'author' => $author,
                'publishedAt' => $publishedAt,
            ];
        }

        return $items;
    }

    /**
     * Les descriptions des agrégateurs contiennent le lien direct vers l'éditeur :
     * on le préfère à l'URL de redirection (vérifiable + sans intermédiaire).
     */
    public static function extractDirectLink(string $descriptionHtml, string $fallback): string
    {
        if (preg_match_all('/<a\s[^>]*href=["\']([^"\']+)["\']/i', $descriptionHtml, $matches)) {
            foreach ($matches[1] as $href) {
                $href = html_entity_decode(trim($href), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (filter_var($href, FILTER_VALIDATE_URL)
                    && str_starts_with($href, 'http')
                    && !str_contains($href, 'news.google.com')
                ) {
                    return $href;
                }
            }
        }

        return $fallback;
    }

    /**
     * @param array{title: string, link: string, description: string, publisher: string, author: string, publishedAt: \DateTimeImmutable} $item
     * @param array{slug: string, feedName: string, url: string, format: string, category: string, place: string, sourceName: string, sourceType: string, score: int, verified: bool, label: string, perItemSource: bool, maxPerPublisher: ?int} $feed
     */
    private function publisherKey(array $item, array $feed): string
    {
        if ($feed['perItemSource']) {
            $name = '' !== $item['publisher'] ? $item['publisher'] : $this->guessPublisher($item['title']);
            if ('' === $name) {
                return 'inconnu';
            }

            return $name;
        }

        return $feed['sourceName'];
    }

    /**
     * @param array{title: string, link: string, description: string, publisher: string, author: string, publishedAt: \DateTimeImmutable} $item
     * @param array{slug: string, feedName: string, url: string, format: string, category: string, place: string, sourceName: string, sourceType: string, score: int, verified: bool, label: string, perItemSource: bool, maxPerPublisher: ?int} $feed
     */
    private function integrate(array $item, array $feed, bool $dryRun): bool
    {
        if ($feed['slug'] === 'reunion-circulation' && !TrafficInfo::isTraffic($item['title'])) { return false; }
        /** @var ArticleRepository $articles */
        $articles = $this->em->getRepository(Article::class);
        $existing = $articles->findOneBy(['sourceUrl' => $item['link']]);
        if (!$existing && ($feed['format'] ?? '') === 'sitemap') {
            $existing = $articles->findOneBy(['title' => [$item['title'], $item['title'].' - Linfo.re']]);
        }
        if (null !== $existing) {
            if (!$dryRun) {
                $existing->setPublishedAt($item['publishedAt']);
                if ($item['publisherZone'] ?? null) { $existing->setPlace($item['publisherZone']); }
                $existing->setCategory(FeedClassifier::categoryFor($item['title'], $existing->getCategory()));
                if (($feed['format'] ?? '') === 'sitemap') {
                    $existing->setSourceUrl($item['link']);
                    $this->enrichment?->enrich($existing);
                }
                $brief = NewsBrief::summarize($item['description'], $existing->getTitle());
                if (mb_strlen($brief) > mb_strlen(NewsBrief::summarize($existing->getContent() ?? '', $existing->getTitle()))) {
                    $existing->setContent($brief)->setExcerpt(NewsBrief::summarize($brief, $existing->getTitle(), 70, 2));
                }
            }
            return false;
        }

        $slug = FeedClassifier::slugify($item['title']);
        if (null !== $articles->findOneBy(['slug' => $slug])) {
            $suffix = 2;
            while (null !== $articles->findOneBy(['slug' => $slug.'-'.$suffix])) {
                ++$suffix;
            }
            $slug = $slug.'-'.$suffix;
        }

        $source = $this->resolveSource($item, $feed, $dryRun);
        $brief = NewsBrief::summarize($item['description'], $item['title']);
        $excerpt = NewsBrief::summarize($brief, $item['title'], 70, 2);
        if ($feed['sourceType'] === 'social') {
            $excerpt = FeedClassifier::excerptOf($item['description']);
            if ($item['author'] !== '') { $excerpt .= ' — par '.$item['author']; }
            $brief = $excerpt;
        }

        $article = (new Article())
            ->setTitle(mb_substr($item['title'], 0, 255))
            ->setSlug($slug)
            ->setExcerpt($excerpt)
            ->setContent($brief)
            ->setCategory(FeedClassifier::categoryFor($item['title'], $feed['category']))
            ->setPlace($item['publisherZone'] ?? FeedClassifier::placeFor($item['title'], $feed['place']))
            ->setSource($source)
            ->setSourceUrl($item['link'])
            ->setPublishedAt($item['publishedAt'])
            ->setVerified(false)
            ->setVerificationLabel($feed['verified'] ? 'Source identifiée' : $feed['label'])
            ->setImportant($item['publishedAt'] > new \DateTimeImmutable('-72 hours'));

        if (!$dryRun) {
            if (($feed['format'] ?? '') === 'sitemap') { $this->enrichment?->enrich($article); }
            $this->em->persist($article);
        }

        return true;
    }

    /**
     * @param array{title: string, link: string, description: string, publisher: string, author: string, publishedAt: \DateTimeImmutable} $item
     * @param array{slug: string, feedName: string, url: string, format: string, category: string, place: string, sourceName: string, sourceType: string, score: int, verified: bool, label: string, perItemSource: bool, maxPerPublisher: ?int} $feed
     */
    private function resolveSource(array $item, array $feed, bool $dryRun = false): Source
    {
        /** @var SourceRepository $sources */
        $sources = $this->em->getRepository(Source::class);

        $name = $feed['sourceName'];
        if ($feed['perItemSource']) {
            $name = '' !== $item['publisher'] ? $item['publisher'] : $this->guessPublisher($item['title']);
        }
        $slug = FeedClassifier::slugify('source '.$name);
        if (isset($this->sourceCache[$slug])) {
            return $this->sourceCache[$slug];
        }
        $existing = $sources->findOneBy(['slug' => $slug]);
        if (null !== $existing) {
            if (!$dryRun && !$existing->getWebsiteUrl()) { $existing->setWebsiteUrl(\App\InfoTrak\PublisherCatalog::websiteFor($name)); }
            return $this->sourceCache[$slug] = $existing;
        }

        $source = (new Source())
            ->setName(mb_substr($name, 0, 150))
            ->setWebsiteUrl(\App\InfoTrak\PublisherCatalog::websiteFor($name))
            ->setSlug($slug)
            ->setType($feed['sourceType'])
            ->setReliabilityScore($feed['score']);

        if (!$dryRun) {
            $this->em->persist($source);
        }

        return $this->sourceCache[$slug] = $source;
    }

    private function guessPublisher(string $title): string
    {
        // Format Google News : « Titre - Nom du média ».
        if (str_contains($title, ' - ')) {
            $pos = strrpos($title, ' - ');
            $candidate = trim(substr($title, $pos + 3));
            if ('' !== $candidate && mb_strlen($candidate) < 60) {
                return $candidate;
            }
        }

        return 'Presse (agrégateur)';
    }
}
