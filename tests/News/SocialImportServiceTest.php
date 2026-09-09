<?php

namespace App\Tests\News;

use App\Entity\Article;
use App\Entity\Source;
use App\Service\SocialImportService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SocialImportServiceTest extends KernelTestCase
{
    private function service(array $payload, string $xToken = 'test-token', string $tiktokToken = ''): SocialImportService
    {
        $container = static::getContainer();
        $em = $container->get('doctrine')->getManager();
        $http = new MockHttpClient(new MockResponse(json_encode($payload, JSON_THROW_ON_ERROR), [
            'http_code' => 200,
            'response_headers' => ['content-type: application/json'],
        ]));

        return new SocialImportService(
            $http,
            $em,
            $em->getRepository(Article::class),
            $em->getRepository(Source::class),
            $xToken,
            '',
            '',
            '',
            '',
            '',
            'v22.0',
            $tiktokToken,
        );
    }

    public function testXImportPersistsPublicPostsAndReusesAuthorSource(): void
    {
        self::bootKernel();
        $tag = bin2hex(random_bytes(5));
        $payload = [
            'data' => [
                ['id' => 'a'.$tag, 'text' => '#ZEVENT collecte solidaire '.$tag, 'author_id' => 'user-'.$tag, 'created_at' => gmdate(DATE_ATOM)],
                ['id' => 'b'.$tag, 'text' => 'Gaming à La Réunion '.$tag, 'author_id' => 'user-'.$tag, 'created_at' => gmdate(DATE_ATOM)],
            ],
            'includes' => ['users' => [['id' => 'user-'.$tag, 'name' => 'Compte Test', 'username' => 'test'.$tag]]],
        ];

        $result = $this->service($payload)->import('x', ['ZEVENT', 'gaming'], 10);
        self::assertSame(2, $result['created']);

        $em = static::getContainer()->get('doctrine')->getManager();
        $articles = $em->getRepository(Article::class)->findBy(['sourceUrl' => [
            'https://x.com/test'.$tag.'/status/a'.$tag,
            'https://x.com/test'.$tag.'/status/b'.$tag,
        ]]);
        self::assertCount(2, $articles);
        self::assertSame($articles[0]->getSource()?->getId(), $articles[1]->getSource()?->getId());
        self::assertSame('Réseau social', $articles[0]->getVerificationLabel());
        self::assertFalse($articles[0]->isVerified());

        $source = $articles[0]->getSource();
        foreach ($articles as $article) { $em->remove($article); }
        $em->flush();
        if (null !== $source) { $em->remove($source); $em->flush(); }
    }

    public function testConnectorStatusAndMissingTokenAreExplicit(): void
    {
        self::bootKernel();
        $service = $this->service([], '');
        self::assertFalse($service->statuses()['x']['configured']);
        self::assertTrue($service->statuses()['reddit']['configured']);
        $this->expectExceptionMessage('X_BEARER_TOKEN n’est pas configuré dans .env.local.');
        $service->import('x', ['gaming']);
    }

    public function testUndatedSocialPostIsRejected(): void
    {
        self::bootKernel();
        $payload = [
            'data' => [['id' => 'undated', 'text' => 'Message sans date', 'author_id' => '1']],
            'includes' => ['users' => [['id' => '1', 'name' => 'Test', 'username' => 'test']]],
        ];
        $this->expectExceptionMessage('publication sans date');
        $this->service($payload)->import('x', ['test']);
    }

    public function testTikTokResearchResultIsStoredWithDirectVideoUrl(): void
    {
        self::bootKernel();
        $tag = bin2hex(random_bytes(5));
        $payload = ['data' => ['videos' => [[
            'id' => '7'.$tag,
            'video_description' => 'Sport à La Réunion '.$tag,
            'create_time' => time(),
            'username' => 'creator'.$tag,
            'region_code' => 'RE',
        ]]]];

        $result = $this->service($payload, '', 'research-test-token')->import('tiktok', ['sport'], 10);
        self::assertSame(1, $result['created']);

        $em = static::getContainer()->get('doctrine')->getManager();
        $url = 'https://www.tiktok.com/@creator'.$tag.'/video/7'.$tag;
        $article = $em->getRepository(Article::class)->findOneBy(['sourceUrl' => $url]);
        self::assertNotNull($article);
        self::assertSame('social', $article->getSource()?->getType());

        $source = $article->getSource();
        $em->remove($article);
        $em->flush();
        if (null !== $source) { $em->remove($source); $em->flush(); }
    }
}
