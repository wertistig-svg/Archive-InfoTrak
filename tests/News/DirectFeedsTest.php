<?php
namespace App\Tests\News;
use App\Entity\Article;
use App\Entity\Source;
use App\News\ArticleEnrichment;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
final class DirectFeedsTest extends TestCase
{
    public function testPublisherRedirectIsFollowedButExternalRedirectIsRejected(): void
    {
        foreach ([true, false] as $sameSite) {
            $calls = [];
            $http = new MockHttpClient(static function ($method, $url) use (&$calls, $sameSite) {
                $calls[] = $url;
                if (str_ends_with($url, '/old')) {
                    return new MockResponse('', ['http_code'=>301, 'response_headers'=>['location: '.($sameSite ? '/new' : 'https://example.com/new')], 'primary_ip'=>'93.184.216.34']);
                }
                return new MockResponse('<div class="article-body"><p>Les travaux reprennent lundi après une interruption de trois jours. La circulation reste limitée pendant les interventions.</p></div>', ['http_code'=>200, 'primary_ip'=>'93.184.216.34']);
            });
            $article = (new Article())->setTitle('Le chantier reprend')->setSource((new Source())->setName('Linfo.re'))->setSourceUrl('https://www.linfo.re/old');
            $service = new ArticleEnrichment($http, new ArrayAdapter());
            self::assertSame($sameSite, $service->enrich($article));
            self::assertCount($sameSite ? 2 : 1, $calls);
            self::assertSame($sameSite, $article->getReadingSummary() !== '');
        }
    }
    public function testChangedHeadlineRequiresCompleteOriginalSlug(): void
    {
        $title='Les travaux de la route reprennent lundi dans la commune';
        self::assertTrue(\App\News\DirectFeeds::matchesTitle('Nouveau titre', $title, 'Source', 'https://example.com/les-travaux-de-la-route-reprennent-lundi-dans-la-commune-12345.html'));
        self::assertFalse(\App\News\DirectFeeds::matchesTitle('Nouveau titre', $title, 'Source', 'https://example.com/les-travaux-de-la-route-reprennent-lundi-dans-la-commune-voisine-12345.html'));
    }
    public function testDirectFeedProvidesDetailsEvenIfArticlePageIsBlocked(): void
    {
        $http = new MockHttpClient(static fn($method,$url)=>new MockResponse(str_ends_with($url,'.xml') ? '<rss><channel><item><title>Le chantier reprend</title><link>https://www.20minutes.fr/article</link><description>Les travaux reprennent lundi après une interruption de trois jours. La circulation reste limitée pendant les interventions.</description></item></channel></rss>' : '', ['http_code'=>str_ends_with($url,'.xml')?200:403,'primary_ip'=>'93.184.216.34']));
        $article=(new Article())->setTitle('Le chantier reprend - 20 Minutes')->setSource((new Source())->setName('20 Minutes'))->setSourceUrl('https://news.google.com/rss/articles/example');
        $service=new ArticleEnrichment($http,new ArrayAdapter());
        self::assertTrue($service->enrich($article));
        self::assertSame('https://www.20minutes.fr/article',$article->getSourceUrl());
        self::assertStringContainsString('Les travaux reprennent lundi',$article->getReadingSummary());
        self::assertSame('Résumé récupéré dans le flux direct',$service->lastStatus);
    }
    public function testUnrelatedFeedArticleCannotReplaceTheRequestedArticle(): void
    {
        $http=new MockHttpClient(static fn()=>new MockResponse('<rss><channel><item><title>Autre événement</title><link>https://www.20minutes.fr/autre</link><description>Le soleil est de retour.</description></item></channel></rss>',['http_code'=>200,'primary_ip'=>'93.184.216.34']));
        $article=(new Article())->setTitle('Le chantier reprend')->setSource((new Source())->setName('20 Minutes'))->setSourceUrl('https://news.google.com/rss/articles/example');
        self::assertFalse((new ArticleEnrichment($http,new ArrayAdapter()))->enrich($article));
        self::assertSame('', $article->getReadingSummary());
    }
}
