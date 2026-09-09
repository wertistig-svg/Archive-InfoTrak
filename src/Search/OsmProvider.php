<?php

namespace App\Search;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Lieux et adresses via OpenStreetMap Nominatim (biais Réunion, sans clé). */
#[AutoconfigureTag('app.search_provider', ['priority' => 10])]
final class OsmProvider implements SearchProviderInterface
{
    public function __construct(private readonly HttpClientInterface $http) {}

    public function search(string $query): array
    {
        $response = $this->http->request('GET', 'https://nominatim.openstreetmap.org/search', [
            'query' => [
                'format' => 'jsonv2',
                'limit' => 4,
                'accept-language' => 'fr',
                'q' => $query,
                // Biais (non strict) vers La Réunion.
                'viewbox' => '55.2,-20.8,55.9,-21.4',
                'bounded' => 0,
            ],
            'headers' => ['User-Agent' => 'InfoTrak/1.0 (contact: admin@example.com)'],
            'timeout' => 6,
            'max_duration' => 8,
        ]);
        $data = $response->toArray();
        if (!\is_array($data)) {
            return [];
        }

        $results = [];
        foreach ($data as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $display = trim((string) ($row['display_name'] ?? ''));
            if ('' === $display) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            $title = '' !== $name ? $name : mb_substr($display, 0, 80);
            $kind = trim((string) (($row['class'] ?? '').' '.($row['type'] ?? '')));
            $lat = $row['lat'] ?? null;
            $lon = $row['lon'] ?? null;
            $url = (null !== $lat && null !== $lon)
                ? sprintf('https://www.openstreetmap.org/?mlat=%s&mlon=%s#map=16/%s/%s', $lat, $lon, $lat, $lon)
                : 'https://www.openstreetmap.org/search?query='.urlencode($query);
            $results[] = new LiveResult(
                'place',
                $title,
                $display.('' !== $kind ? sprintf(' (%s).', trim($kind)) : '.'),
                'OpenStreetMap',
                $url,
            );
        }

        return $results;
    }
}
