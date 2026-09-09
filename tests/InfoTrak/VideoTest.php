<?php

namespace App\Tests\InfoTrak;

use App\Tests\TestAuthTrait;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class VideoTest extends WebTestCase
{
    use TestAuthTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $kernel = self::bootKernel();
        $application = new Application($kernel);
        $application->setAutoExit(false);
        $application->run(new ArrayInput(['command' => 'app:seed']));
        self::ensureKernelShutdown();
    }

    private function attachVideo(string $slug, string $url): void
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);
        $application->setAutoExit(false);
        $code = $application->run(new ArrayInput([
            'command' => 'app:article:set-video',
            'slug' => $slug,
            'url' => $url,
        ]), new BufferedOutput());
        $this->assertSame(0, $code);
        self::ensureKernelShutdown();
    }

    public function testHomeShowsInlinePlayerWithoutExternalRedirect(): void
    {
        // Vidéos attachées en test uniquement : les données du site restent honnêtes.
        $this->attachVideo('emploi-ouest-ile', 'https://example.com/videos/offres.mp4');
        $this->attachVideo('associations-jeunes', 'https://www.youtube.com/watch?v=aqz-KE-bpKQ');

        $client = $this->createAuthenticatedClient();
        $client->request('POST', '/preferences', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'topics' => ['La Réunion', 'Cybersécurité', 'Emploi', 'Jeux', 'Environnement'],
            'zones' => [],
            'frequency' => 'live',
        ]));
        $this->assertResponseIsSuccessful();

        $crawler = $client->request('GET', '/?demo=1&view=all');

        $this->assertResponseIsSuccessful();
        // Bouton dépliable + iframe paresseuse (data-src, pas de src chargée d'office).
        $this->assertGreaterThanOrEqual(1, $crawler->filter('.video-toggle')->count());
        $lazyIframe = $crawler->filter('[data-video-iframe][data-src*="youtube-nocookie.com"]');
        $this->assertGreaterThanOrEqual(1, $lazyIframe->count());
        $this->assertSame('', $lazyIframe->first()->attr('src') ?? '');
        // Aucun lien de visionnage externe : on regarde dans l'app.
        $this->assertSame(0, $crawler->filter('a[href*="youtube.com/watch"]')->count());
        $this->assertSame(0, $crawler->filter('a[href*="youtu.be/"]')->count());
    }

    public function testArticlePageRendersFullPlayer(): void
    {
        $this->attachVideo('emploi-ouest-ile', 'https://example.com/videos/offres.mp4');
        $this->attachVideo('associations-jeunes', 'https://www.youtube.com/watch?v=aqz-KE-bpKQ');

        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/article/emploi-ouest-ile');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('[data-video-player] video[src$="offres.mp4"]');

        $client->request('GET', '/article/associations-jeunes');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('[data-video-player] iframe[src*="youtube-nocookie.com/embed/"]');
    }

    public function testSetVideoCommandAttachesAndRejects(): void
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);
        $application->setAutoExit(false);

        $output = new BufferedOutput();
        $code = $application->run(new ArrayInput([
            'command' => 'app:article:set-video',
            'slug' => 'emploi-ouest-ile',
            'url' => 'https://example.com/videos/offres.mp4',
        ]), $output);
        $this->assertSame(0, $code);

        $em = static::getContainer()->get('doctrine')->getManager();
        $article = $em->getRepository(\App\Entity\Article::class)->findOneBy(['slug' => 'emploi-ouest-ile']);
        $this->assertSame('https://example.com/videos/offres.mp4', $article->getVideoUrl());

        $badOutput = new BufferedOutput();
        $badCode = $application->run(new ArrayInput([
            'command' => 'app:article:set-video',
            'slug' => 'emploi-ouest-ile',
            'url' => 'https://example.com/pas-une-video',
        ]), $badOutput);
        $this->assertSame(1, $badCode);
        self::ensureKernelShutdown();
    }
}
