<?php

namespace App\Search;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Discussions et liens partagés via l'API publique Reddit (sans clé). */
#[AutoconfigureTag('app.search_provider', ['priority' => 0])]
final class RedditProvider implements SearchProviderInterface
{
    public function __construct(private readonly HttpClientInterface $http) {}

    public function search(string $query): array
    {
        $response = $this->http->request('GET', 'https://www.reddit.com/search.json', [
            'query' => ['q' => $query, 'limit' => 5, 'sort' => 'relevance', 'type' => 'link', 'include_over_18' => 'off'],
            'headers' => ['User-Agent' => 'InfoTrak/1.0 by admin@example.com'],
            'timeout' => 6,
            'max_duration' => 8,
        ]);
        $data = $response->toArray();
        $children = $data['data']['children'] ?? null;
        if (!\is_array($children)) {
            return [];
        }

        $results = [];
        foreach ($children as $child) {
            if (\count($results) >= 3) {
                break;
            }
            $post = \is_array($child) ? ($child['data'] ?? []) : [];
            if (!\is_array($post)) {
                continue;
            }
            $title = trim((string) ($post['title'] ?? ''));
            $permalink = (string) ($post['permalink'] ?? '');
            if ('' === $title || '' === $permalink) {
                continue;
            }
            $sub = trim((string) ($post['subreddit_name_prefixed'] ?? 'Reddit'));
            $score = (int) ($post['score'] ?? 0);
            $selftext = trim((string) ($post['selftext'] ?? ''));
            if (mb_strlen($selftext) > 220) {
                $selftext = mb_substr($selftext, 0, 220).'…';
            }
            $created = null;
            if (isset($post['created_utc']) && is_numeric($post['created_utc'])) {
                $created = gmdate(\DateTimeInterface::ATOM, (int) $post['created_utc']);
            }
            $results[] = new LiveResult(
                'social',
                $title,
                '' !== $selftext ? $selftext : sprintf('Discussion %s · %d vote(s).', $sub, $score),
                'Reddit '.$sub,
                'https://www.reddit.com'.$permalink,
                $created,
            );
        }

        return $results;
    }
}
