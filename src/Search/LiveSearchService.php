<?php

namespace App\Search;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/** Interroge les API web et fusionne leurs résultats (panne d'un provider = ignorée, mise en cache 15 min). */
final class LiveSearchService
{
    private const MAX_RESULTS = 12;

    /** @param iterable<SearchProviderInterface> $providers */
    public function __construct(
        #[AutowireIterator('app.search_provider')]
        private readonly iterable $providers,
        private readonly CacheInterface $cache,
    ) {}

    /** @return array<int, array{type: string, title: string, summary: string, source: string, url: string, publishedAt: ?string}> */
    public function search(string $query): array
    {
        $query = trim($query);
        if ('' === $query) {
            return [];
        }

        return $this->cache->get('live_search_v2_'.md5(mb_strtolower($query)), function (ItemInterface $item) use ($query): array {
            $item->expiresAfter(900);
            $merged = [];
            foreach ($this->providers as $provider) {
                try {
                    foreach ($provider->search($query) as $result) {
                        $merged[] = $result->toArray();
                        if (\count($merged) >= self::MAX_RESULTS) {
                            break 2;
                        }
                    }
                } catch (\Throwable) {
                    continue;
                }
            }

            return $merged;
        });
    }
}
