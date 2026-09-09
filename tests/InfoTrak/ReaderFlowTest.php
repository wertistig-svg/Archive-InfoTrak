<?php

namespace App\Tests\InfoTrak;

use App\Entity\Article;
use App\Entity\Notification;
use App\Entity\Source;
use App\Entity\User;
use App\Service\NotificationService;
use App\Service\PreferenceService;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Scénarios de régression de la bêta : sessions, filtres, pagination et confidentialité. */
final class ReaderFlowTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $tag;
    private int $sourceId;
    private array $ids = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->tag = 'reader-'.bin2hex(random_bytes(6));
        $em = static::getContainer()->get('doctrine')->getManager();
        $source = (new Source())->setName($this->tag)->setSlug($this->tag)->setType('press')->setWebsiteUrl('https://example.com');
        $em->persist($source);
        for ($i = 0; $i < 15; ++$i) {
            $a = (new Article())->setSource($source)->setTitle($this->tag.' Offre '.$i)->setSlug($this->tag.'-'.$i)
                ->setCategory('Emploi')->setPlace('La Réunion')->setSourceUrl('https://example.com/'.$this->tag.'/'.$i)
                ->setPublishedAt(new \DateTimeImmutable('2030-01-01 12:00:00'))
                ->setImportant(true)->setDemo(14 === $i);
            $em->persist($a);
        }
        $em->flush();
        $this->sourceId = $source->getId();
        $this->ids = array_map(static fn ($a) => $a->getId(), $em->getRepository(Article::class)->findBy(['source' => $source], ['id' => 'ASC']));
        $this->csrf();
    }

    protected function tearDown(): void
    {
        $em = static::getContainer()->get('doctrine')->getManager();
        $em->clear();
        $em->createQuery('DELETE FROM App\Entity\Notification n WHERE n.article IN (:ids)')->setParameter('ids', $this->ids)->execute();
        $em->createQuery('DELETE FROM App\Entity\Article a WHERE a.source = :source')->setParameter('source', $this->sourceId)->execute();
        $em->createQuery('DELETE FROM App\Entity\Source s WHERE s.id = :source')->setParameter('source', $this->sourceId)->execute();
        parent::tearDown();
    }

    private function csrf(): void
    {
        $page = $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        $this->client->setServerParameter('HTTP_X_CSRF_TOKEN', $page->filter('meta[name="csrf-token"]')->attr('content'));
    }

    private function post(string $url, array $payload = []): array
    {
        $this->client->request('POST', $url, [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
        return json_decode($this->client->getResponse()->getContent(), true) ?? [];
    }

    public function testGuestPreferencesPersistWithoutRestrictingReadingToImportantNews(): void
    {
        $em = static::getContainer()->get('doctrine')->getManager();
        $em->find(Article::class, $this->ids[0])->setImportant(false);
        $em->flush();
        $this->post('/preferences', ['topics' => ['Emploi'], 'zones' => ['La Réunion'], 'frequency' => 'important']);
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/preferences');
        $prefs = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame(['Emploi'], $prefs['topics']);
        $this->client->request('GET', '/', ['q' => $this->tag, 'page' => 2]);
        self::assertSelectorExists('[data-article-id="'.$this->ids[0].'"]');
    }

    public function testSessionsDoNotSharePreferences(): void
    {
        $this->post('/preferences', ['topics' => ['Jeux'], 'zones' => ['Japon'], 'frequency' => 'live']);
        self::assertResponseIsSuccessful();
        $this->client->restart();
        $this->client->request('GET', '/preferences');
        $prefs = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame([], $prefs['topics']);
        self::assertSame([], $prefs['zones']);
    }

    public function testExpandedTopicsPersistAndCommunityPageIsPublic(): void
    {
        $result = $this->post('/preferences', ['topics' => ['Sciences', 'Sport', 'Streaming & créateurs'], 'zones' => [], 'frequency' => 'live']);
        self::assertResponseIsSuccessful();
        self::assertSame(['Sciences', 'Sport', 'Streaming & créateurs'], $result['topics']);
        $this->client->request('GET', '/communautes', ['networks' => ['x', 'reddit', 'tiktok'], 'community' => 'gaming']);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[value="x"][checked]');
        self::assertSelectorExists('input[value="reddit"][checked]');
        self::assertSelectorExists('input[value="tiktok"][checked]');
        self::assertSelectorExists('option[value="gaming"][selected]');
        self::assertSelectorTextContains('h1', 'Réseaux & communautés');
        $this->client->request('GET', '/communautes?networks=x');
        self::assertResponseStatusCodeSame(404);
    }

    public function testSocialPostsAreFilteredAndHiddenOnCommunityPage(): void
    {
        $em = static::getContainer()->get('doctrine')->getManager();
        $em->find(Source::class, $this->sourceId)->setType('social');
        $em->find(Article::class, $this->ids[0])->setTitle($this->tag.' gaming solidaire')->setSourceUrl('https://x.com/test/status/'.$this->tag)->setPublishedAt(new \DateTimeImmutable('-1 hour'));
        $em->find(Article::class, $this->ids[1])->setTitle($this->tag.' gaming solidaire')->setSourceUrl('https://reddit.com/r/test/comments/'.$this->tag)->setPublishedAt(new \DateTimeImmutable('-2 hours'));
        $em->flush();
        $this->client->request('GET', '/communautes', ['networks' => ['x'], 'community' => 'gaming', 'q' => $this->tag]);
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, '.social-post');
        $this->post('/api/feedback', ['articleId' => $this->ids[0], 'interested' => false]);
        $this->client->request('GET', '/communautes', ['networks' => ['x'], 'q' => $this->tag]);
        self::assertSelectorCount(0, '.social-post');
        self::assertSelectorExists('.community-empty');
    }

    public function testPaginationAndDemoExclusion(): void
    {
        $this->client->request('GET', '/', ['q' => $this->tag, 'topic' => 'Emploi', 'zone' => 'La Réunion']);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#visibleCount', '14');
        self::assertSelectorCount(12, '.news-card');
        self::assertSelectorNotExists('[data-article-id="'.$this->ids[14].'"]');
        $this->client->request('GET', '/', ['q' => $this->tag, 'page' => 2]);
        self::assertSelectorCount(2, '.news-card');
        $this->client->request('GET', '/', ['q' => $this->tag, 'demo' => 1]);
        self::assertSelectorTextContains('#visibleCount', '15');
        self::assertSelectorExists('.trust-label.is-demo');
        $this->client->request('GET', '/article/'.$this->tag.'-14');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.demo-notice');
    }

    public function testRejectedArticlesStayExcludedAfterReloadAndNoResults(): void
    {
        foreach (array_slice($this->ids, 0, 14) as $id) {
            $this->post('/api/feedback', ['articleId' => $id, 'interested' => false]);
            self::assertResponseIsSuccessful();
        }
        $this->client->request('GET', '/', ['q' => $this->tag, 'view' => 'all']);
        self::assertSelectorCount(0, '.news-card');
        self::assertSelectorExists('#feedEmpty:not([hidden])');
        $this->client->request('GET', '/', ['q' => $this->tag, 'topic' => 'Emploi']);
        self::assertSelectorCount(0, '.news-card');
    }

    public function testSearchAndZoneFiltersAreCombinedAndWildcardsAreLiteral(): void
    {
        $this->client->request('GET', '/', ['q' => $this->tag, 'zone' => 'Japon']);
        self::assertSelectorCount(0, '.news-card');
        $this->client->request('GET', '/', ['q' => $this->tag.'%']);
        self::assertSelectorCount(0, '.news-card');
        $this->client->request('GET', '/', ['q' => $this->tag.'_']);
        self::assertSelectorCount(0, '.news-card');
    }

    public function testCsrfAndInvalidPayloadsAreRejected(): void
    {
        $this->client->setServerParameter('HTTP_X_CSRF_TOKEN', 'forged');
        $this->post('/api/feedback', ['articleId' => $this->ids[0], 'interested' => false]);
        self::assertResponseStatusCodeSame(403);
        $this->csrf();
        $this->post('/api/feedback', ['articleId' => $this->ids[0], 'interested' => 'false']);
        self::assertResponseStatusCodeSame(422);
        $this->post('/preferences', ['topics' => [['invalid']], 'frequency' => 'live']);
        self::assertResponseStatusCodeSame(422);
        $this->client->request('GET', '/', ['q' => $this->tag, 'page' => 2]);
        self::assertSelectorExists('[data-article-id="'.$this->ids[0].'"]');
    }

    public function testNotificationsArePrivateAndReadingOneAccountDoesNotAffectAnother(): void
    {
        $em = static::getContainer()->get('doctrine')->getManager();
        $a = (new User())->setGoogleId($this->tag.'-a')->setEmail($this->tag.'-a@example.com')->setDisplayName('Compte A');
        $b = (new User())->setGoogleId($this->tag.'-b')->setEmail($this->tag.'-b@example.com')->setDisplayName('Compte B');
        $em->persist($a); $em->persist($b); $em->flush();
        $service = static::getContainer()->get(NotificationService::class);
        $preferences = static::getContainer()->get(PreferenceService::class);
        $article = $em->find(Article::class, $this->ids[0]);
        $first = $service->notifyForArticle($article, $preferences->getOrCreate($a->getUserIdentifier()));
        $second = $service->notifyForArticle($article, $preferences->getOrCreate($b->getUserIdentifier()));
        self::assertNotSame($first->getId(), $second->getId());
        self::assertNull($service->notifyForArticle($article, $preferences->getOrCreate($a->getUserIdentifier())), 'Un doublon ne compte pas comme une nouvelle notification.');
        $foreignId = $second->getId();
        $this->client->loginUser($a); $this->csrf();
        $this->client->request('GET', '/api/notifications');
        $items = json_decode($this->client->getResponse()->getContent(), true)['items'];
        self::assertNotContains($foreignId, array_column($items, 'id'));
        $this->post('/api/notifications/'.$foreignId.'/read');
        self::assertResponseStatusCodeSame(404);
        $this->post('/api/notifications/read-all');
        self::assertResponseIsSuccessful();
        $em = static::getContainer()->get('doctrine')->getManager();
        $em->clear();
        self::assertFalse($em->find(Notification::class, $foreignId)->isRead());
    }

    public function testDemoAndRejectedArticlesDoNotGenerateAlerts(): void
    {
        $this->post('/api/feedback', ['articleId' => $this->ids[0], 'interested' => false]);
        $em = static::getContainer()->get('doctrine')->getManager();
        $feedback = $em->getRepository(\App\Entity\ArticleFeedback::class)->findOneBy(['article' => $this->ids[0]]);
        $preference = static::getContainer()->get(PreferenceService::class)->getOrCreate($feedback->getOwnerKey());
        $service = static::getContainer()->get(NotificationService::class);
        self::assertNull($service->notifyForArticle($em->find(Article::class, $this->ids[0]), $preference));
        self::assertNull($service->notifyForArticle($em->find(Article::class, $this->ids[14]), $preference));
    }
}
