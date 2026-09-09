<?php

namespace App\Search;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Actualités récentes multi-sources via le flux public Google News (sans clé). */
#[AutoconfigureTag('app.search_provider', ['priority' => 30])]
final class GoogleNewsProvider implements SearchProviderInterface
{
    public function __construct(private readonly HttpClientInterface $http) {}

    public function search(string $query): array
    {
        $response = $this->http->request('GET', 'https://news.google.com/rss/search', [
            'query' => ['q' => $query, 'hl' => 'fr', 'gl' => 'FR', 'ceid' => 'FR:fr'],
            'timeout' => 6,
            'max_duration' => 8,
        ]);
        $xml = @simplexml_load_string($response->getContent());
        if (false === $xml) {
            return [];
        }

        $results = [];
        $items = $xml->channel->item ?? [];
        $count = 0;
        foreach ($items as $item) {
            if ($count >= 5) {
                break;
            }
            $rawTitle = trim((string) ($item->title ?? ''));
            if ('' === $rawTitle) {
                continue;
            }
            // Format habituel : « Titre de l'article - Nom du média ».
            $source = trim((string) ($item->source ?? ''));
            $title = $rawTitle;
            if ('' === $source && str_contains($rawTitle, ' - ')) {
                $pos = strrpos($rawTitle, ' - ');
                $title = trim(substr($rawTitle, 0, $pos));
                $source = trim(substr($rawTitle, $pos + 3));
            }
            $link = trim((string) ($item->link ?? ''));
            if ('' === $link || !filter_var($link, FILTER_VALIDATE_URL)) {
                continue;
            }
            $publishedAt = null;
            $ts = strtotime((string) ($item->pubDate ?? ''));
            if (false !== $ts) {
                $publishedAt = gmdate(\DateTimeInterface::ATOM, $ts);
            }
            $results[] = new LiveResult(
                'news',
                $title,
                '' !== $source ? sprintf('Via %s%s.', $source, null !== $publishedAt ? ' · '.gmdate('d/m H:i', $ts) : '') : 'Actualité récente.',
                '' !== $source ? $source : 'Google News',
                $link,
                $publishedAt,
            );
            ++$count;
        }

        return $results;
    }
}
