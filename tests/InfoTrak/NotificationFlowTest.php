<?php

namespace App\Tests\InfoTrak;

use App\Tests\TestAuthTrait;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Input\ArrayInput;

class NotificationFlowTest extends WebTestCase
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

        // Notifications régénérées au format actuel (les anciennes BDD de test ont l'ancien format).
        $container = static::getContainer();
        $em = $container->get('doctrine')->getManager();
        $em->createQuery('DELETE FROM App\Entity\Notification n')->execute();
        $preferences = $container->get(\App\Service\PreferenceService::class);
        $container->get(\App\Service\NotificationService::class)
            ->generateForRecent($preferences->getOrCreate('default'));

        self::ensureKernelShutdown();
    }

    protected function tearDown(): void
    {
        $this->removeNotificationFixture();
        parent::tearDown();
    }

    public function testListExposesClearSummarySourceAndArticleLink(): void
    {
        $client = $this->createAuthenticatedClient();
        $client->request('GET', '/api/notifications');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertNotEmpty($data['items']);
        $first = $data['items'][0];
        $this->assertStringStartsWith('/article/', (string) $first['link']);
        $this->assertNotEmpty($first['source'], 'Chaque notification montre sa source.');
        $this->assertStringNotContainsString('[', (string) $first['message'], 'Plus de préfixe brut [catégorie].');
    }

    public function testReadDecreasesUnreadCount(): void
    {
        $client = $this->createAuthenticatedClient();
        $client->request('GET', '/api/notifications');
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertGreaterThan(0, $data['unread']);

        $target = null;
        foreach ($data['items'] as $item) {
            if (!$item['isRead']) {
                $target = $item;
                break;
            }
        }
        $this->assertNotNull($target);

        $client->request('POST', '/api/notifications/'.$target['id'].'/read');
        $this->assertResponseIsSuccessful();
        $after = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame($data['unread'] - 1, $after['unread']);
    }
}
