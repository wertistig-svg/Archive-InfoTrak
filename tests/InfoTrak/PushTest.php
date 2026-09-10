<?php
namespace App\Tests\InfoTrak;

use App\Service\PushService;
use Doctrine\DBAL\Connection;
use Minishlink\WebPush\VAPID;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PushTest extends WebTestCase
{
    private function subscription(string $suffix = 'test'): array
    {
        return ['endpoint' => 'https://fcm.googleapis.com/fcm/send/'.$suffix, 'keys' => ['p256dh' => VAPID::createVapidKeys()['publicKey'], 'auth' => rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=')]];
    }

    public function testRejectsUnsafeEndpointsAndMalformedKeys(): void
    {
        $data = $this->subscription();
        self::assertTrue(PushService::validSubscription($data));
        foreach (['http://fcm.googleapis.com/x', 'https://127.0.0.1/x', 'https://fcm.googleapis.com.evil.test/x', 'https://fcm.googleapis.com:8443/x', 'https://user:pass@fcm.googleapis.com/x', 'https://example.com/x'] as $endpoint) {
            self::assertFalse(PushService::validSubscription(array_replace($data, ['endpoint' => $endpoint])));
        }
        $data['keys']['auth'] = 'short';
        self::assertFalse(PushService::validSubscription($data));
    }

    public function testKeysPersistEncryptedAndOwnershipIsEnforced(): void
    {
        self::bootKernel();
        $db = self::getContainer()->get(Connection::class);
        $service = self::getContainer()->get(PushService::class);
        $keys = $service->keys();
        self::assertSame($keys, $service->keys());
        self::assertStringNotContainsString($keys['privateKey'], $db->fetchOne('SELECT private_key FROM push_config WHERE id = 1'));
        $data = $this->subscription(bin2hex(random_bytes(8)));
        try {
            $service->subscribe('push-test-owner', $data);
            $service->subscribe('push-test-owner', $data);
            self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM push_subscription WHERE endpoint_hash = ?', [hash('sha256', $data['endpoint'])]));
            $service->unsubscribe('other-owner', $data['endpoint']);
            self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM push_subscription WHERE endpoint_hash = ?', [hash('sha256', $data['endpoint'])]));
            $this->expectException(\InvalidArgumentException::class);
            $service->subscribe('other-owner', $data);
        } finally { $service->unsubscribe('push-test-owner', $data['endpoint']); }
    }

    public function testGuestPageAndCsrfProtection(): void
    {
        $client = static::createClient();
        $page = $client->request('GET', '/notifications-telephone');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#pushEnable');
        $csrf = $page->filter('meta[name="csrf-token"]')->attr('content');
        $client->request('POST', '/api/push/subscribe', [], [], ['CONTENT_TYPE' => 'application/json'], '{}');
        self::assertResponseStatusCodeSame(403);
        $client->request('POST', '/api/push/subscribe', [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $csrf], '{"endpoint":"http://localhost"}');
        self::assertResponseStatusCodeSame(422);
        $client->request('GET', '/api/push/key');
        self::assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('publicKey', $data);
        self::assertArrayNotHasKey('privateKey', $data);
    }
}
