<?php

namespace App\Tests\News;

use App\Entity\Article;
use App\News\NewsImportService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class NewsImportServiceTest extends KernelTestCase
{
    public function testExistingRssArticleGetsCompleteSummaryAndPublisherZone(): void
    {
        self::bootKernel();
        $tag = bin2hex(random_bytes(6));
        $rss = $this->fakeRss($tag);
        $this->serviceWithRss($rss, $this->fakeFeed())->import();
        $rss = str_replace('78 coureurs au départ du prologue.', '78 coureurs au départ du prologue. Le départ est prévu ce jeudi. Le parcours comporte trois étapes.', $rss);
        $rss = str_replace('<item>', '<item><category>International</category>', $rss);
        $stats = $this->serviceWithRss($rss, $this->fakeFeed())->import();
        self::assertSame(0, $stats['created']);
        $em = static::getContainer()->get('doctrine')->getManager();
        $article = $em->getRepository(Article::class)->findOneBy(['sourceUrl' => 'https://example.com/articles/tour-reunion-'.$tag]);
        self::assertStringContainsString('Le parcours comporte trois étapes.', $article->getContent());
        self::assertSame('International', $article->getPlace());
        foreach ($em->getRepository(Article::class)->findBy(['sourceUrl' => ['https://example.com/articles/tour-reunion-'.$tag, 'https://example.com/articles/arnaques-sms-'.$tag]]) as $created) { $em->remove($created); }
        $em->flush();
    }

    public function testSpipDateIsPublicationDateAndUndatedItemsAreSkipped(): void
    {
        self::bootKernel();
        $tag = bin2hex(random_bytes(6));
        $date = new \DateTimeImmutable('-2 days');
        $rss = str_replace('<rss version="2.0">', '<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/">', $this->fakeRss($tag));
        $rss = preg_replace('/<pubDate>.*?<\/pubDate>/', '<dc:date>'.$date->format(DATE_ATOM).'</dc:date>', $rss, 1);
        $rss = preg_replace('/<pubDate>.*?<\/pubDate>/', '', $rss);
        $stats = $this->serviceWithRss($rss, $this->fakeFeed())->import();
        self::assertSame(1, $stats['created']);
        $em = static::getContainer()->get('doctrine')->getManager();
        $article = $em->getRepository(Article::class)->findOneBy(['sourceUrl' => 'https://example.com/articles/tour-reunion-'.$tag]);
        self::assertSame($date->getTimestamp(), $article->getPublishedAt()->getTimestamp());
        $em->remove($article); $em->flush();
    }

    public function testLongSourceUrlIsPreservedWithoutBlockingImport(): void
    {
        self::bootKernel();
        $tag = bin2hex(random_bytes(6));
        $url = 'https://example.com/articles/'.str_repeat('long-link-', 100).$tag;
        $rss = str_replace('https://example.com/articles/tour-reunion-'.$tag, $url, $this->fakeRss($tag));
        $stats = $this->serviceWithRss($rss, $this->fakeFeed())->import();
        self::assertSame(2, $stats['created']);
        $em = static::getContainer()->get('doctrine')->getManager();
        $article = $em->getRepository(Article::class)->findOneBy(['sourceUrl' => $url]);
        self::assertNotNull($article);
        self::assertSame($url, $article->getSourceUrl());
        foreach ($em->getRepository(Article::class)->findBy(['sourceUrl' => [$url, 'https://example.com/articles/arnaques-sms-'.$tag]]) as $created) { $em->remove($created); }
        $em->flush();
    }

    private function serviceWithRss(string $rss, array $feed): NewsImportService
    {
        $container = static::getContainer();
        $http = new MockHttpClient(new MockResponse($rss));

        return new NewsImportService(
            $container->get('doctrine')->getManager(),
            $http,
            [$feed],
        );
    }

    private function fakeFeed(): array
    {
        return [
            'slug' => 'test-reunion', 'feedName' => 'Flux test', 'url' => 'https://example.com/feed.xml',
            'category' => 'La Réunion', 'place' => 'La Réunion',
            'sourceName' => 'Source Test', 'sourceType' => 'press', 'score' => 85,
            'verified' => true, 'label' => 'Vérifiée', 'perItemSource' => false,
        ];
    }

    private function fakeRss(string $tag): string
    {
        $now = gmdate('D, d M Y H:i:s \G\M\T');
        return <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <rss version="2.0"><channel>
          <item>
            <title>Le Tour de La Réunion démarre à Saint-Denis {$tag}</title>
            <link>https://example.com/articles/tour-reunion-{$tag}</link>
            <pubDate>{$now}</pubDate>
            <description><![CDATA[<p>78 coureurs au départ du prologue.</p>]]></description>
          </item>
          <item>
            <title>Alerte aux arnaques par faux SMS de livraison {$tag}</title>
            <link>https://example.com/articles/arnaques-sms-{$tag}</link>
            <pubDate>{$now}</pubDate>
            <description>Les escrocs se font passer pour des livreurs.</description>
          </item>
        </channel></rss>
        XML;
    }

    public function testImportCreatesClassifiedArticlesThenSkipsDuplicates(): void
    {
        self::bootKernel();
        $tag = substr(md5(uniqid('', true)), 0, 6);
        $service = $this->serviceWithRss($this->fakeRss($tag), $this->fakeFeed());

        $stats = $service->import(null, 10);

        $this->assertSame(2, $stats['created']);
        $em = static::getContainer()->get('doctrine')->getManager();
        $repo = $em->getRepository(Article::class);

        $tour = $repo->findOneBy(['sourceUrl' => "https://example.com/articles/tour-reunion-{$tag}"]);
        $this->assertNotNull($tour);
        $this->assertSame('La Réunion', $tour->getCategory());
        $this->assertSame('La Réunion', $tour->getPlace());
        $this->assertFalse($tour->isVerified(), 'Un flux de presse ne certifie pas les faits.');
        $this->assertSame('Source identifiée', $tour->getTrustLabel());
        $this->assertTrue($tour->isImportant());
        $this->assertSame("https://example.com/articles/tour-reunion-{$tag}", $tour->getSourceUrl());
        $this->assertStringContainsString('78 coureurs', $tour->getExcerpt());

        $arnaque = $repo->findOneBy(['sourceUrl' => "https://example.com/articles/arnaques-sms-{$tag}"]);
        $this->assertNotNull($arnaque);
        // Classement par mot-clé prioritaire sur la catégorie par défaut du flux.
        $this->assertSame('Cybersécurité', $arnaque->getCategory());

        // Deuxième passage : doublons détectés, rien de créé.
        $second = $this->serviceWithRss($this->fakeRss($tag), $this->fakeFeed())->import(null, 10);
        $this->assertSame(0, $second['created']);
        $this->assertSame(2, $second['skipped']);

        // Nettoyage : ces articles datés de « maintenant » pollueraient le fil des autres tests.
        foreach ($repo->findBy(['sourceUrl' => [
            "https://example.com/articles/tour-reunion-{$tag}",
            "https://example.com/articles/arnaques-sms-{$tag}",
        ]]) as $created) {
            $em->remove($created);
        }
        $em->flush();
    }

    private function socialFeed(): array
    {
        return [
            'slug' => 'test-social', 'feedName' => 'Flux social test', 'url' => 'https://example.com/social.xml',
            'format' => 'atom', 'category' => 'La Réunion', 'place' => 'La Réunion',
            'sourceName' => 'Réseau Test', 'sourceType' => 'social', 'score' => 60,
            'verified' => false, 'label' => 'Réseau social', 'perItemSource' => false, 'maxPerPublisher' => null,
        ];
    }

    private function atomRss(string $tag): string
    {
        $now = gmdate(\DateTimeInterface::ATOM);
        return <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <feed xmlns="http://www.w3.org/2005/Atom">
          <title>r/test</title>
          <entry>
            <title>Quel budget pour vivre à Saint-Denis {$tag} ?</title>
            <link rel="alternate" href="https://www.reddit.com/r/test/comments/aaa/sujet-{$tag}/"/>
            <updated>{$now}</updated>
            <author><name>/u/toto974</name></author>
            <content type="html">&lt;p&gt;Bonjour, je déménage bientôt.&lt;/p&gt;</content>
          </entry>
          <entry>
            <title></title>
            <link rel="alternate" href="https://www.reddit.com/r/test/comments/bbb/sujet-{$tag}-bis/"/>
            <updated>{$now}</updated>
            <content type="html">&lt;p&gt;Météo du jour à Saint-Pierre : grand soleil {$tag} et mer calme.&lt;/p&gt;</content>
          </entry>
        </feed>
        XML;
    }

    public function testAtomFeedImportsWithAuthorAndDerivedTitle(): void
    {
        self::bootKernel();
        $tag = substr(md5(uniqid('', true)), 0, 6);
        $service = $this->serviceWithRss($this->atomRss($tag), $this->socialFeed());

        $stats = $service->import(null, 10);

        $this->assertSame(2, $stats['created']);
        $em = static::getContainer()->get('doctrine')->getManager();
        $repo = $em->getRepository(Article::class);

        $discussion = $repo->findOneBy(['sourceUrl' => "https://www.reddit.com/r/test/comments/aaa/sujet-{$tag}/"]);
        $this->assertNotNull($discussion);
        $this->assertSame('Réseau social', $discussion->getVerificationLabel());
        $this->assertFalse($discussion->isVerified());
        $this->assertStringContainsString('/u/toto974', $discussion->getExcerpt());

        // Sans titre : titre dérivé du texte.
        $fallback = $repo->findOneBy(['sourceUrl' => "https://www.reddit.com/r/test/comments/bbb/sujet-{$tag}-bis/"]);
        $this->assertNotNull($fallback);
        $this->assertStringContainsString('soleil', $fallback->getTitle());

        foreach ([$discussion, $fallback] as $created) {
            $em->remove($created);
        }
        $em->flush();
    }

    public function testUndatedSocialEntriesAreNotImportedAsRecentPosts(): void
    {
        self::bootKernel();
        $xml = preg_replace('/<updated>.*?<\/updated>/', '', $this->atomRss('undated-'.bin2hex(random_bytes(4))));
        $stats = $this->serviceWithRss($xml, $this->socialFeed())->import(null, 10, true);
        self::assertSame(0, $stats['created']);
    }

    public function testPublisherCapDiversifiesSources(): void
    {
        self::bootKernel();
        $tag = substr(md5(uniqid('', true)), 0, 6);
        $now = gmdate('D, d M Y H:i:s \G\M\T');
        $items = '';
        for ($i = 1; $i <= 5; ++$i) {
            $items .= "<item><title>Info capée {$tag} numéro {$i}</title><link>https://example.com/cap-{$tag}-{$i}</link><pubDate>{$now}</pubDate><description>Texte.</description><source>MemePresse</source></item>";
        }
        $rss = "<?xml version=\"1.0\" encoding=\"UTF-8\"?><rss version=\"2.0\"><channel>{$items}</channel></rss>";
        $feed = $this->fakeFeed();
        $feed['maxPerPublisher'] = 2;
        $service = $this->serviceWithRss($rss, $feed);

        $stats = $service->import(null, 10);

        $this->assertSame(2, $stats['created']);
        $this->assertSame(3, $stats['skipped']);

        $em = static::getContainer()->get('doctrine')->getManager();
        foreach ($em->getRepository(Article::class)->findAll() as $article) {
            if (str_contains((string) $article->getSourceUrl(), "cap-{$tag}-")) {
                $em->remove($article);
            }
        }
        $em->flush();
    }
}
