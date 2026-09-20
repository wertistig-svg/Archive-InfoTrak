<?php
namespace App\Tests\News;

use App\Entity\Article;
use App\Entity\Source;
use App\News\ArticleEnrichment;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class PublisherEnrichmentTest extends TestCase
{
    public function testImazBodyExcludesCommentsAndTruncatedMetadata(): void
    {
        $html = '<meta property="og:description" content="Le temps devient…"><div class="article__body"><p>Un front froid est attendu lundi sur les pentes du sud de La Réunion, selon les dernières prévisions publiées par les météorologues. (Photo : Exemple)</p><p>Une baisse des températures reste possible mardi.</p><div id="comments"><p>Faux commentaire à exclure.</p></div><p class="article__tags">Publicité et mots clés.</p></div>';
        $text = ArticleEnrichment::extract($html);
        self::assertStringContainsString('reste possible mardi', $text);
        self::assertStringNotContainsString('Faux commentaire', $text);
        self::assertStringNotContainsString('Photo', $text);
        self::assertStringNotContainsString('Publicité', $text);
    }

    public function testExactPublisherLinkOnly(): void
    {
        $html = '<a href="https://evil.test/vol">Le front froid</a><a href="/meteo/front">Le front froid</a>';
        self::assertSame('https://imazpress.com/meteo/front', ArticleEnrichment::findLink($html, 'https://imazpress.com', 'Le front froid - Imaz Press', 'Imaz Press'));
        self::assertNull(ArticleEnrichment::findLink($html, 'https://imazpress.com', 'Autre événement', 'Imaz Press'));
        self::assertFalse(ArticleEnrichment::allowed('https://imazpress.com.evil.test/'));
        self::assertFalse(ArticleEnrichment::allowed('https://www.linkedin.com/jobs'));
    }

    public function testGoogleLinkResolvedAndArticleEnrichedWithoutComments(): void
    {
        $page = '<meta property="og:image" content="https://imazpress.com/image.jpg"><div class="article__body"><p>Un front froid est attendu lundi sur les pentes du sud de La Réunion, selon les dernières prévisions publiées par les météorologues.</p><p>La prudence reste recommandée.</p></div>';
        $http = new MockHttpClient(static fn ($method, $url) => new MockResponse(str_ends_with($url, '/actualite') ? '<a href="/meteo/front">Le front froid</a>' : $page, ['http_code'=>200, 'primary_ip'=>'93.184.216.34']));
        $service = new ArticleEnrichment($http, new ArrayAdapter());
        $article = (new Article())->setTitle('Le front froid - Imaz Press')->setSource((new Source())->setName('Imaz Press'))->setSourceUrl('https://news.google.com/rss/article/example');
        self::assertTrue($service->enrich($article));
        self::assertSame('https://imazpress.com/meteo/front', $article->getSourceUrl());
        self::assertStringContainsString('attendu lundi', $article->getContent());
        self::assertSame('https://imazpress.com/image.jpg', $article->getImageUrl());
    }

    public function testOversizedImageDoesNotPreventSummary(): void
    {
        $page = '<meta property="og:image" content="https://imazpress.com/'.str_repeat('x', 600).'.jpg"><meta name="description" content="Un front froid est attendu lundi sur les pentes du sud de La Réunion, selon les dernières prévisions publiées par les météorologues.">';
        $service = new ArticleEnrichment(new MockHttpClient(new MockResponse($page, ['http_code'=>200, 'primary_ip'=>'93.184.216.34'])), new ArrayAdapter());
        $article = (new Article())->setTitle('Météo')->setSourceUrl('https://imazpress.com/meteo/front');
        self::assertTrue($service->enrich($article));
        self::assertNull($article->getImageUrl());
        self::assertStringContainsString('attendu lundi', $article->getContent());
    }
}
