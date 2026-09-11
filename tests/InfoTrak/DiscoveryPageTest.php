<?php
namespace App\Tests\InfoTrak;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DiscoveryPageTest extends WebTestCase
{
    public function testPublicPages(): void
    {
        $client = self::createClient();
        $client->request('GET', '/sources');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'France Travail');
        $client->request('GET', '/circulation');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'La Circulation');
        self::assertSelectorExists('a[href="https://www.infotrafic.re/fr"]');
    }
}
