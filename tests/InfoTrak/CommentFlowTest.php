<?php

namespace App\Tests\InfoTrak;

use App\Tests\TestAuthTrait;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Input\ArrayInput;

class CommentFlowTest extends WebTestCase
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

    private function firstArticleId(): int
    {
        $em = static::getContainer()->get('doctrine')->getManager();

        return (int) $em->getConnection()->fetchOne('SELECT id FROM article ORDER BY id LIMIT 1');
    }

    public function testListPostAndDeleteComment(): void
    {
        $client = $this->createAuthenticatedClient('Marie L.');
        $articleId = $this->firstArticleId();

        $client->request('GET', "/api/articles/{$articleId}/comments");
        $this->assertResponseIsSuccessful();
        $before = \count(json_decode($client->getResponse()->getContent(), true)['items']);

        $client->request('POST', "/api/articles/{$articleId}/comments", [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'content' => 'Très bonne info, merci pour le résumé !',
        ]));
        $this->assertResponseStatusCodeSame(201);
        $created = json_decode($client->getResponse()->getContent(), true)['item'];
        $this->assertSame('Marie L.', $created['displayName']);
        $this->assertTrue($created['mine']);

        $client->request('GET', "/api/articles/{$articleId}/comments");
        $after = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount($before + 1, $after['items']);

        $client->request('DELETE', "/api/articles/{$articleId}/comments/{$created['id']}");
        $this->assertResponseIsSuccessful();

        $client->request('GET', "/api/articles/{$articleId}/comments");
        $this->assertCount($before, json_decode($client->getResponse()->getContent(), true)['items']);
    }

    public function testEmptyAndTooLongAreRejected(): void
    {
        $client = $this->createAuthenticatedClient();
        $articleId = $this->firstArticleId();

        $client->request('POST', "/api/articles/{$articleId}/comments", [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['content' => '   ']));
        $this->assertResponseStatusCodeSame(422);

        $client->request('POST', "/api/articles/{$articleId}/comments", [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['content' => str_repeat('x', 501)]));
        $this->assertResponseStatusCodeSame(422);
    }

    public function testCannotDeleteOthersComments(): void
    {
        [$client, $author] = $this->createAuthenticatedClientWithUser('Auteur');
        $articleId = $this->firstArticleId();

        $client->request('POST', "/api/articles/{$articleId}/comments", [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['content' => 'Mon commentaire']));
        $id = json_decode($client->getResponse()->getContent(), true)['item']['id'];

        // Second compte sur le même client (un seul client par test).
        $em = $client->getContainer()->get('doctrine')->getManager();
        $other = (new \App\Entity\User())
            ->setGoogleId('test-'.bin2hex(random_bytes(8)))
            ->setEmail(sprintf('test-%s@example.com', bin2hex(random_bytes(8))))
            ->setDisplayName('Autre');
        $em->persist($other);
        $em->flush();
        $client->loginUser($other);

        $client->request('DELETE', "/api/articles/{$articleId}/comments/{$id}");
        $this->assertResponseStatusCodeSame(403);

        // Nettoyage par l'auteur.
        $client->loginUser($author);
        $client->request('DELETE', "/api/articles/{$articleId}/comments/{$id}");
        $this->assertResponseIsSuccessful();
    }

    public function testArticlePageHasXLayoutBackButtonAndComments(): void
    {
        $client = $this->createAuthenticatedClient();
        $em = $client->getContainer()->get('doctrine')->getManager();
        $article = $em->getRepository(\App\Entity\Article::class)->findOneBy(['slug' => 'vivatech-10-entrepreneurs-pei']);
        // État de départ garanti (résidu d'un run précédent possible).
        $article->setImageUrl(null);
        $em->flush();
        $client->request('GET', '/article/vivatech-10-entrepreneurs-pei');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('a.back-btn[href="/"]');
        $this->assertSelectorExists('.x-topbar');
        $this->assertSelectorExists('.x-post');
        $this->assertSelectorExists('[data-xbar]');
        $this->assertSelectorExists('#comments');
        $this->assertSelectorExists('#commentForm');

        // Commentaires repliés par défaut : bouton pour déplier.
        $em = $client->getContainer()->get('doctrine')->getManager();
        $vivatech = $em->getRepository(\App\Entity\Article::class)->findOneBy(['slug' => 'vivatech-10-entrepreneurs-pei']);
        $client->request('POST', '/api/articles/'.$vivatech->getId().'/comments', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['content' => 'Commentaire de test pour le repli']));
        $postedId = json_decode($client->getResponse()->getContent(), true)['item']['id'] ?? null;
        $this->assertNotNull($postedId);
        $client->request('GET', '/article/vivatech-10-entrepreneurs-pei');
        $this->assertSelectorExists('#commentsToggle');
        $this->assertSelectorExists('#commentList[hidden]');
        $client->request('DELETE', '/api/articles/'.$vivatech->getId()."/comments/{$postedId}");
        $this->assertResponseIsSuccessful();
        // Recharger la page : le crawler pointe sinon sur la réponse JSON du DELETE.
        $client->request('GET', '/article/vivatech-10-entrepreneurs-pei');
        // Favicon réel du média avec repli sur la lettre.
        $this->assertSelectorExists('.x-head img.favicon[src*="s2/favicons"]');
        $this->assertSelectorNotExists('.source-trust .source-logo');
        // Sans image : pas de cadre média ; avec image : aperçu affiché.
        $this->assertSelectorNotExists('.x-media');

        $article = $em->getRepository(\App\Entity\Article::class)->findOneBy(['slug' => 'vivatech-10-entrepreneurs-pei']);
        $article->setImageUrl('https://example.com/une.jpg');
        $em->flush();
        $client->request('GET', '/article/vivatech-10-entrepreneurs-pei');
        $this->assertSelectorExists('.x-media img[src="https://example.com/une.jpg"]');
        $article->setImageUrl(null);
        $em->flush();
    }
}
