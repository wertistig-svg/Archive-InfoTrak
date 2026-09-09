<?php

namespace App\Tests\Search;

use App\Tests\TestAuthTrait;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Input\ArrayInput;

class SearchControllerTest extends WebTestCase
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

    public function testShortQueryIsRejectedWithoutCallingApis(): void
    {
        $client = $this->createAuthenticatedClient();
        $client->request('GET', '/api/search', ['q' => 'ab']);

        $this->assertResponseStatusCodeSame(422);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    public function testMissingQueryIsRejected(): void
    {
        $client = $this->createAuthenticatedClient();
        $client->request('GET', '/api/search');

        $this->assertResponseStatusCodeSame(422);
    }

    public function testLiveSearchReturnsNormalizedResults(): void
    {
        $client = $this->createAuthenticatedClient();
        $client->request('GET', '/api/search', ['q' => 'boutique La Réunion']);

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('boutique La Réunion', $data['query']);
        $this->assertIsArray($data['results']);
        foreach ($data['results'] as $item) {
            $this->assertArrayHasKey('title', $item);
            $this->assertArrayHasKey('summary', $item);
            $this->assertArrayHasKey('source', $item);
            $this->assertArrayHasKey('url', $item);
            $this->assertNotEmpty($item['source'], 'Chaque résultat doit montrer sa source.');
            $this->assertNotFalse(filter_var($item['url'], FILTER_VALIDATE_URL));
        }
    }
}
