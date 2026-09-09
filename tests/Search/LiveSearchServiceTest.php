<?php

namespace App\Tests\Search;

use App\Search\GoogleNewsProvider;
use App\Search\LiveResult;
use App\Search\LiveSearchService;
use App\Search\OsmProvider;
use App\Search\RedditProvider;
use App\Search\SearchProviderInterface;
use App\Search\WikipediaProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class LiveSearchServiceTest extends TestCase
{
    public function testWikipediaNormalizesTitleSummarySourceAndUrl(): void
    {
        $payload = ['reunion', ['La Réunion'], ['Île française de l’océan Indien.'], ['https://fr.wikipedia.org/wiki/La_R%C3%A9union']];
        $provider = new WikipediaProvider(new MockHttpClient(new MockResponse(json_encode($payload))));

        $results = $provider->search('reunion');

        $this->assertCount(1, $results);
        $this->assertSame('wiki', $results[0]->type);
        $this->assertSame('La Réunion', $results[0]->title);
        $this->assertSame('Wikipédia', $results[0]->source);
        $this->assertStringContainsString('océan Indien', $results[0]->summary);
        $this->assertSame('https://fr.wikipedia.org/wiki/La_R%C3%A9union', $results[0]->url);
    }

    public function testOsmBuildsPlaceResultWithMapLink(): void
    {
        $payload = [[
            'display_name' => 'Saint-Denis, La Réunion, France',
            'name' => 'Saint-Denis',
            'class' => 'place',
            'type' => 'town',
            'lat' => '-20.8823',
            'lon' => '55.4504',
        ]];
        $provider = new OsmProvider(new MockHttpClient(new MockResponse(json_encode($payload))));

        $results = $provider->search('saint-denis reunion');

        $this->assertCount(1, $results);
        $this->assertSame('place', $results[0]->type);
        $this->assertSame('Saint-Denis', $results[0]->title);
        $this->assertSame('OpenStreetMap', $results[0]->source);
        $this->assertStringContainsString('openstreetmap.org', $results[0]->url);
    }

    public function testRedditNormalizesPostWithSubredditSource(): void
    {
        $payload = ['data' => ['children' => [['data' => [
            'title' => 'La Réunion déploie la fibre',
            'permalink' => '/r/france/comments/abc/la_reunion/',
            'subreddit_name_prefixed' => 'r/france',
            'score' => 42,
            'selftext' => '',
            'created_utc' => 1725600000,
        ]]]]];
        $provider = new RedditProvider(new MockHttpClient(new MockResponse(json_encode($payload))));

        $results = $provider->search('reunion fibre');

        $this->assertCount(1, $results);
        $this->assertSame('social', $results[0]->type);
        $this->assertSame('Reddit r/france', $results[0]->source);
        $this->assertSame('https://www.reddit.com/r/france/comments/abc/la_reunion/', $results[0]->url);
    }

    public function testGoogleNewsExtractsPublisherSource(): void
    {
        $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0"><channel>
          <item>
            <title>Fibre : La Réunion accélère - Zinfos974</title>
            <link>https://news.google.com/articles/xyz</link>
            <pubDate>Sat, 06 Sep 2026 07:00:00 GMT</pubDate>
            <source>Zinfos974</source>
          </item>
        </channel></rss>
        XML;
        $provider = new GoogleNewsProvider(new MockHttpClient(new MockResponse($xml)));

        $results = $provider->search('fibre reunion');

        $this->assertCount(1, $results);
        $this->assertSame('news', $results[0]->type);
        $this->assertSame('Fibre : La Réunion accélère - Zinfos974', $results[0]->title);
        $this->assertSame('Zinfos974', $results[0]->source);
    }

    public function testServiceMergesAndIgnoresFailingProvider(): void
    {
        $ok = new class() implements SearchProviderInterface {
            public function search(string $query): array
            {
                return [new LiveResult('news', 'Titre', 'Résumé', 'Source', 'https://example.com/1')];
            }
        };
        $ko = new class() implements SearchProviderInterface {
            public function search(string $query): array
            {
                throw new \RuntimeException('API en panne');
            }
        };

        $service = new LiveSearchService([$ok, $ko], new ArrayAdapter());
        $results = $service->search('reunion');

        $this->assertCount(1, $results);
        $this->assertSame('Titre', $results[0]['title']);
        $this->assertSame('Source', $results[0]['source']);
        // Second appel servi par le cache, même résultat.
        $this->assertSame($results, $service->search('reunion'));
    }
}
