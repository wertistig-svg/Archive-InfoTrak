<?php

namespace App\Search;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Résumé encyclopédique via Wikipédia (opensearch, sans clé). */
#[AutoconfigureTag('app.search_provider', ['priority' => 20])]
final class WikipediaProvider implements SearchProviderInterface
{
    public function __construct(private readonly HttpClientInterface $http) {}

    public function search(string $query): array
    {
        $response = $this->http->request('GET', 'https://fr.wikipedia.org/w/api.php', [
            'query' => ['action' => 'opensearch', 'search' => $query, 'limit' => 4, 'namespace' => 0, 'format' => 'json'],
            'timeout' => 6,
            'max_duration' => 8,
        ]);
        $data = $response->toArray();
        if (!isset($data[1]) || !\is_array($data[1])) {
            return [];
        }

        $results = [];
        foreach ($data[1] as $i => $rawTitle) {
            if (\count($results) >= 3) {
                break;
            }
            $title = trim((string) $rawTitle);
            $summary = trim((string) ($data[2][$i] ?? ''));
            $url = trim((string) ($data[3][$i] ?? ''));
            if ('' === $title || '' === $url || !filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }
            $results[] = new LiveResult(
                'wiki',
                $title,
                '' !== $summary ? $summary : 'Article encyclopédique Wikipédia.',
                'Wikipédia',
                $url,
            );
        }

        return $results;
    }
}
