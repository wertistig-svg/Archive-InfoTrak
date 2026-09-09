<?php

namespace App\Tests\InfoTrak;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use App\Tests\TestAuthTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Input\ArrayInput;

class BackendTest extends WebTestCase
{
    use TestAuthTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $kernel = self::bootKernel();
        $application = new Application($kernel);
        $application->setAutoExit(false);
        $application->run(new ArrayInput(['command' => 'app:seed']));
        $this->createNotificationFixture();
        self::ensureKernelShutdown();
    }

    protected function tearDown(): void
    {
        $this->removeNotificationFixture();
        parent::tearDown();
    }

    public function testHomeRendersDynamicFeed(): void
    {
        $client = $this->createAuthenticatedClient();
        $client->request('POST', '/preferences', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'topics' => ['La Réunion', 'Cybersécurité', 'Emploi', 'Jeux', 'Environnement'],
            'zones' => [],
            'frequency' => 'live',
        ]));
        $this->assertResponseIsSuccessful();
        $client->request('GET', '/?demo=1');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('title', 'InfoTrak');
        // Fil dynamique : article seedé + lien vers la source.
        $this->assertSelectorExists('.story');
        $this->assertSelectorExists('a.source-link');
        // Feedback "cette info vous intéresse ?".
        $this->assertSelectorExists('.feedback-row');
        // Panneau notifications + pastille + menu profil (fermables au clic extérieur / Escape côté JS).
        $this->assertSelectorExists('#notifPanel');
        $this->assertSelectorExists('#notificationButton');
        $this->assertSelectorExists('#notifCount');
        $this->assertSelectorExists('#avatarButton');
        $this->assertSelectorExists('#userMenu');
        $this->assertSelectorExists('[data-menu="preferences"]');
        $this->assertSelectorExists('[data-menu="theme"]');
        $this->assertSelectorExists('[data-menu="read-all"]');
        $this->assertSelectorExists('a[href="/compte/reseaux"]');
        // Panneau latéral repliable via le hamburger ; bas du sidebar supprimé.
        $this->assertSelectorExists('#sidebar');
        $this->assertSelectorExists('.mobile-menu[aria-controls="sidebar"]');
        $this->assertSelectorExists('#sidebarBackdrop');
        $this->assertSelectorNotExists('.settings-link');
        $this->assertSelectorNotExists('.user-card');
        $this->assertSelectorExists('.sidebar-network-grid a[href*="tiktok"]');
        $this->assertSelectorNotExists('.more-topics');
        $this->assertSelectorNotExists('a[href*="community=zevent"]');
        // Liste numérotée sans pastille-lettre.
        $this->assertSelectorExists('.feed-item .feed-number');
        $this->assertSelectorNotExists('.feed-item .source-logo');
    }

    public function testSocialAccountPageRequiresLoginAndShowsAllNetworks(): void
    {
        $anonymous = static::createClient();
        $anonymous->request('GET', '/compte/reseaux');
        self::assertResponseRedirects();
        self::ensureKernelShutdown();

        $client = $this->createAuthenticatedClient();
        $client->request('GET', '/compte/reseaux');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Mes réseaux');
        self::assertSelectorCount(6, '.social-account-card');
        self::assertSelectorTextContains('.social-account-grid', 'TikTok');
    }

    public function testPreferencesPersistAndGenerateNotifications(): void
    {
        $client = $this->createAuthenticatedClient();
        $client->request('POST', '/preferences', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'topics' => ['La Réunion', 'Chine'],
            'zones' => ['La Réunion', 'Chine'],
            'frequency' => 'live',
        ]));

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['ok']);
        $this->assertContains('La Réunion', $data['topics']);

        $client->request('GET', '/api/notifications');
        $this->assertResponseIsSuccessful();
        $notifs = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('items', $notifs);
        $this->assertNotEmpty($notifs['items']);
    }

    public function testFeedbackHidesUnwantedContent(): void
    {
        $client = $this->createAuthenticatedClient();
        $em = static::getContainer()->get('doctrine')->getManager();
        $articleId = (int) $em->getConnection()->fetchOne('SELECT id FROM article ORDER BY id LIMIT 1');

        $client->request('POST', '/api/feedback', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'articleId' => $articleId,
            'interested' => false,
        ]));

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['ok']);
        $this->assertFalse($data['interested']);
    }

    public function testArticlePageHasSourceLink(): void
    {
        $client = $this->createAuthenticatedClient();
        $client->request('GET', '/article/vivatech-10-entrepreneurs-pei');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('a.source-link');
        // Encart source fiable + articles liés.
        $this->assertSelectorExists('.source-trust');
        $this->assertSelectorExists('.related-list');
    }

    public function testDashboardShowsStatsMovedOutOfHome(): void
    {
        $client = $this->createAuthenticatedClient();
        $crawler = $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        // Cartes sorties de l'accueil…
        $this->assertSame(0, $crawler->filter('main .metrics')->count());
        // …mais présentes dans l'onglet InfoTrak, lié depuis le tiroir.
        $this->assertSelectorExists('a[href="/infotrak"]');
        $this->assertSelectorExists('#liveResults');

        $client->request('GET', '/infotrak');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'en chiffres');
        $this->assertSelectorCount(4, '.dashboard-metric');
        $this->assertSelectorTextContains('.dashboard-note', 'exemples de test sont exclus');
    }
}
