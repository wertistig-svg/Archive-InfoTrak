<?php

namespace App\Service;

use App\Entity\Article;
use App\Entity\Source;
use App\News\FeedClassifier;
use App\Repository\ArticleRepository;
use App\Repository\SourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Collecte des publications publiques via les API officielles configurées. */
final class SocialImportService
{
    public const NETWORKS = ['x', 'threads', 'instagram', 'facebook', 'tiktok'];

    /** @var array<string, Source> */
    private array $sourceCache = [];
    /** @var array<string, true> */
    private array $articleUrls = [];
    /** @var array<string, true> */
    private array $articleSlugs = [];

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly EntityManagerInterface $em,
        private readonly ArticleRepository $articles,
        private readonly SourceRepository $sources,
        #[Autowire('%env(string:X_BEARER_TOKEN)%')] private readonly string $xToken,
        #[Autowire('%env(string:THREADS_ACCESS_TOKEN)%')] private readonly string $threadsToken,
        #[Autowire('%env(string:INSTAGRAM_ACCESS_TOKEN)%')] private readonly string $instagramToken,
        #[Autowire('%env(string:INSTAGRAM_USER_ID)%')] private readonly string $instagramUserId,
        #[Autowire('%env(string:FACEBOOK_ACCESS_TOKEN)%')] private readonly string $facebookToken,
        #[Autowire('%env(string:FACEBOOK_PAGE_IDS)%')] private readonly string $facebookPageIds,
        #[Autowire('%env(string:META_GRAPH_VERSION)%')] private readonly string $metaVersion,
        #[Autowire('%env(string:TIKTOK_RESEARCH_TOKEN)%')] private readonly string $tiktokResearchToken,
    ) {}

    /** @return array<string, array{configured: bool, requirement: string}> */
    public function statuses(): array
    {
        return [
            'x' => ['configured' => '' !== trim($this->xToken), 'requirement' => 'X_BEARER_TOKEN'],
            'instagram' => ['configured' => '' !== trim($this->instagramToken) && '' !== trim($this->instagramUserId), 'requirement' => 'INSTAGRAM_ACCESS_TOKEN + INSTAGRAM_USER_ID'],
            'facebook' => ['configured' => '' !== trim($this->facebookToken) && [] !== $this->pageIds(), 'requirement' => 'FACEBOOK_ACCESS_TOKEN + FACEBOOK_PAGE_IDS'],
            'threads' => ['configured' => '' !== trim($this->threadsToken), 'requirement' => 'THREADS_ACCESS_TOKEN'],
            'reddit' => ['configured' => true, 'requirement' => 'Flux RSS publics'],
            'tiktok' => ['configured' => '' !== trim($this->tiktokResearchToken), 'requirement' => 'TIKTOK_RESEARCH_TOKEN (accès Research approuvé)'],
        ];
    }

    /** @return array{network: string, created: int, skipped: int, errors: string[]} */
    public function import(string $network, array $terms, int $limit = 30, bool $dryRun = false): array
    {
        if (!in_array($network, self::NETWORKS, true)) { throw new \InvalidArgumentException('Réseau inconnu.'); }
        $limit = max(1, min(100, $limit));
        $terms = array_values(array_unique(array_filter(array_map(static fn ($term) => mb_substr(trim((string) $term), 0, 60), $terms))));
        if ([] === $terms) { throw new \InvalidArgumentException('Ajoutez au moins un sujet à collecter.'); }

        $this->sourceCache = $this->articleUrls = $this->articleSlugs = [];
        $posts = match ($network) {
            'x' => $this->fetchX($terms, $limit),
            'threads' => $this->fetchThreads($terms, $limit),
            'instagram' => $this->fetchInstagram($terms, $limit),
            'facebook' => $this->fetchFacebook($terms, $limit),
            'tiktok' => $this->fetchTikTok($terms, $limit),
        };
        $created = $skipped = 0;
        foreach ($posts as $post) {
            if ($this->store($network, $post, $dryRun)) { ++$created; } else { ++$skipped; }
        }
        if (!$dryRun) { $this->em->flush(); }
        return ['network' => $network, 'created' => $created, 'skipped' => $skipped, 'errors' => []];
    }

    private function fetchX(array $terms, int $limit): array
    {
        $this->requireValue($this->xToken, 'X_BEARER_TOKEN');
        $query = '('.implode(' OR ', array_map([$this, 'xTerm'], array_slice($terms, 0, 8))).') lang:fr -is:retweet';
        $payload = $this->json('https://api.x.com/2/tweets/search/recent', [
            'auth_bearer' => $this->xToken,
            'query' => ['query' => $query, 'max_results' => max(10, min(100, $limit)), 'tweet.fields' => 'created_at,author_id', 'expansions' => 'author_id', 'user.fields' => 'name,username'],
        ]);
        $users = [];
        foreach ($payload['includes']['users'] ?? [] as $user) { $users[$user['id']] = $user; }
        $posts = [];
        foreach ($payload['data'] ?? [] as $item) {
            $user = $users[$item['author_id'] ?? ''] ?? [];
            $username = trim((string) ($user['username'] ?? 'inconnu'));
            $posts[] = $this->post((string) ($item['text'] ?? ''), 'https://x.com/'.$username.'/status/'.rawurlencode((string) $item['id']), (string) ($item['created_at'] ?? ''), (string) ($user['name'] ?? '@'.$username), $username);
        }
        return $posts;
    }

    private function fetchThreads(array $terms, int $limit): array
    {
        $this->requireValue($this->threadsToken, 'THREADS_ACCESS_TOKEN');
        $posts = [];
        foreach (array_slice($terms, 0, 5) as $term) {
            $payload = $this->json('https://graph.threads.net/v1.0/keyword_search', [
                'auth_bearer' => $this->threadsToken,
                'query' => ['q' => $term, 'search_type' => 'RECENT', 'fields' => 'id,text,username,permalink,timestamp', 'limit' => min(25, $limit)],
            ]);
            foreach ($payload['data'] ?? [] as $item) {
                $posts[] = $this->post((string) ($item['text'] ?? ''), (string) ($item['permalink'] ?? ''), (string) ($item['timestamp'] ?? ''), '@'.(string) ($item['username'] ?? 'inconnu'), (string) ($item['username'] ?? 'inconnu'));
                if (count($posts) >= $limit) { break 2; }
            }
        }
        return $posts;
    }

    private function fetchInstagram(array $terms, int $limit): array
    {
        $this->requireValue($this->instagramToken, 'INSTAGRAM_ACCESS_TOKEN');
        $this->requireValue($this->instagramUserId, 'INSTAGRAM_USER_ID');
        $posts = [];
        foreach (array_slice($terms, 0, 5) as $term) {
            $tag = preg_replace('/[^\p{L}\p{N}_]/u', '', $term);
            if ('' === $tag) { continue; }
            $tagResult = $this->json($this->graphUrl('ig_hashtag_search'), [
                'auth_bearer' => $this->instagramToken,
                'query' => ['user_id' => $this->instagramUserId, 'q' => $tag],
            ]);
            $tagId = (string) ($tagResult['data'][0]['id'] ?? '');
            if ('' === $tagId) { continue; }
            $payload = $this->json($this->graphUrl($tagId.'/recent_media'), [
                'auth_bearer' => $this->instagramToken,
                'query' => ['user_id' => $this->instagramUserId, 'fields' => 'id,caption,permalink,timestamp,username', 'limit' => min(25, $limit)],
            ]);
            foreach ($payload['data'] ?? [] as $item) {
                $posts[] = $this->post((string) ($item['caption'] ?? ''), (string) ($item['permalink'] ?? ''), (string) ($item['timestamp'] ?? ''), '@'.(string) ($item['username'] ?? 'inconnu'), (string) ($item['username'] ?? 'inconnu'));
                if (count($posts) >= $limit) { break 2; }
            }
        }
        return $posts;
    }

    private function fetchFacebook(array $terms, int $limit): array
    {
        $this->requireValue($this->facebookToken, 'FACEBOOK_ACCESS_TOKEN');
        $posts = [];
        foreach ($this->pageIds() as $pageId) {
            $payload = $this->json($this->graphUrl(rawurlencode($pageId).'/posts'), [
                'auth_bearer' => $this->facebookToken,
                'query' => ['fields' => 'id,message,created_time,permalink_url,from', 'limit' => min(25, $limit)],
            ]);
            foreach ($payload['data'] ?? [] as $item) {
                $text = trim((string) ($item['message'] ?? ''));
                if ('' === $text) { continue; }
                $normalized = FeedClassifier::normalize($text);
                if (!array_any($terms, static fn ($term) => str_contains($normalized, FeedClassifier::normalize($term)))) { continue; }
                $from = (string) ($item['from']['name'] ?? $pageId);
                $posts[] = $this->post($text, (string) ($item['permalink_url'] ?? 'https://www.facebook.com/'.$item['id']), (string) ($item['created_time'] ?? ''), $from, (string) ($item['from']['id'] ?? $pageId));
                if (count($posts) >= $limit) { break 2; }
            }
        }
        return $posts;
    }

    private function fetchTikTok(array $terms, int $limit): array
    {
        $this->requireValue($this->tiktokResearchToken, 'TIKTOK_RESEARCH_TOKEN');
        $posts = [];
        foreach (array_slice($terms, 0, 5) as $term) {
            $payload = $this->json('https://open.tiktokapis.com/v2/research/video/query/?fields=id,video_description,create_time,username,region_code', [
                'auth_bearer' => $this->tiktokResearchToken,
                'json' => [
                    'query' => ['and' => [['operation' => 'EQ', 'field_name' => 'keyword', 'field_values' => [$term]]]],
                    'start_date' => (new \DateTimeImmutable('-7 days'))->format('Ymd'),
                    'end_date' => (new \DateTimeImmutable())->format('Ymd'),
                    'max_count' => min(100, $limit),
                    'is_random' => false,
                ],
            ], 'POST');
            foreach ($payload['data']['videos'] ?? [] as $item) {
                $text = trim((string) ($item['video_description'] ?? ''));
                $username = trim((string) ($item['username'] ?? 'inconnu'));
                $id = trim((string) ($item['id'] ?? ''));
                $timestamp = filter_var($item['create_time'] ?? null, FILTER_VALIDATE_INT);
                if ('' === $text || '' === $id || false === $timestamp) { continue; }
                $posts[] = $this->post($text, 'https://www.tiktok.com/@'.rawurlencode($username).'/video/'.rawurlencode($id), '@'.$timestamp, '@'.$username, $username);
                if (count($posts) >= $limit) { break 2; }
            }
        }
        return $posts;
    }

    private function store(string $network, array $post, bool $dryRun): bool
    {
        if ('' === $post['text'] || !filter_var($post['url'], FILTER_VALIDATE_URL) || isset($this->articleUrls[$post['url']]) || null !== $this->articles->findOneBy(['sourceUrl' => $post['url']])) { return false; }
        $this->articleUrls[$post['url']] = true;
        $sourceSlug = 'social-'.$network.'-'.substr(hash('sha256', $post['authorKey']), 0, 18);
        $source = $this->sourceCache[$sourceSlug] ?? $this->sources->findOneBy(['slug' => $sourceSlug]) ?? (new Source())->setSlug($sourceSlug)->setName(mb_substr($post['author'], 0, 150))->setType('social')->setReliabilityScore(50)->setWebsiteUrl($this->networkWebsite($network));
        $this->sourceCache[$sourceSlug] = $source;
        if (null === $source->getId() && !$dryRun) { $this->em->persist($source); }
        $title = mb_substr(trim((string) preg_replace('/\s+/', ' ', $post['text'])), 0, 180);
        $slug = FeedClassifier::slugify($network.'-'.$post['authorKey'].'-'.$title);
        if (isset($this->articleSlugs[$slug]) || null !== $this->articles->findOneBy(['slug' => $slug])) { $slug .= '-'.substr(hash('sha256', $post['url']), 0, 8); }
        $this->articleSlugs[$slug] = true;
        $article = (new Article())->setTitle($title)->setSlug($slug)->setExcerpt(mb_substr($post['text'], 0, 1000))->setContent($post['text'])
            ->setCategory(FeedClassifier::categoryFor($post['text'], 'Société'))->setPlace(FeedClassifier::placeFor($post['text'], 'International'))
            ->setSource($source)->setSourceUrl($post['url'])->setPublishedAt($post['publishedAt'])->setVerified(false)->setVerificationLabel('Réseau social')->setImportant($post['publishedAt'] > new \DateTimeImmutable('-72 hours'));
        if (!$dryRun) { $this->em->persist($article); }
        return true;
    }

    private function post(string $text, string $url, string $date, string $author, string $authorKey): array
    {
        if ('' === trim($date)) { throw new \RuntimeException('La plateforme a renvoyé une publication sans date.'); }
        try { $publishedAt = new \DateTimeImmutable($date); } catch (\Throwable) { throw new \RuntimeException('La plateforme a renvoyé une date invalide.'); }
        if ($publishedAt > new \DateTimeImmutable('+5 minutes')) { throw new \RuntimeException('La plateforme a renvoyé une date future.'); }
        return ['text' => trim($text), 'url' => trim($url), 'publishedAt' => $publishedAt, 'author' => trim($author) ?: 'Compte public', 'authorKey' => trim($authorKey) ?: hash('sha256', $url)];
    }

    private function json(string $url, array $options, string $method = 'GET'): array
    {
        $response = $this->http->request($method, $url, $options + ['timeout' => 12, 'max_duration' => 20]);
        $payload = $response->toArray(false);
        if ($response->getStatusCode() >= 400) { throw new \RuntimeException((string) ($payload['error']['message'] ?? 'Erreur HTTP '.$response->getStatusCode())); }
        if (!is_array($payload)) { throw new \RuntimeException('Réponse JSON invalide.'); }
        return $payload;
    }

    private function xTerm(string $term): string { return preg_match('/^[#@][\p{L}\p{N}_]+$/u', $term) ? $term : '"'.str_replace('"', '', $term).'"'; }
    private function graphUrl(string $path): string { return 'https://graph.facebook.com/'.trim($this->metaVersion, '/').'/'.ltrim($path, '/'); }
    private function pageIds(): array { return array_values(array_filter(array_map('trim', explode(',', $this->facebookPageIds)), static fn ($id) => (bool) preg_match('/^[A-Za-z0-9._-]{2,100}$/', $id))); }
    private function requireValue(string $value, string $name): void { if ('' === trim($value)) { throw new \RuntimeException($name.' n’est pas configuré dans .env.local.'); } }
    private function networkWebsite(string $network): string { return match ($network) { 'x' => 'https://x.com', 'threads' => 'https://www.threads.com', 'instagram' => 'https://www.instagram.com', 'facebook' => 'https://www.facebook.com', 'tiktok' => 'https://www.tiktok.com' }; }
}
