<?php

namespace App\Tests\InfoTrak;

use App\Entity\Article;
use App\Entity\Source;
use App\InfoTrak\CommunityCatalog;
use App\Service\CommunityService;
use PHPUnit\Framework\TestCase;

final class CommunityServiceTest extends TestCase
{
    private function post(string $title, string $url, string $date = '2026-09-07 12:00:00'): Article
    {
        return (new Article())->setTitle($title)->setSourceUrl($url)->setSource((new Source())->setName('Source test')->setType('social'))
            ->setPublishedAt(new \DateTimeImmutable($date));
    }

    private function summarize(array $posts, array $networks = [], string $community = '', string $query = ''): array
    {
        return (new CommunityService())->summarize($posts, $networks, $community, $query, new \DateTimeImmutable('2026-09-01'), new \DateTimeImmutable('2026-09-08'));
    }

    public function testNetworksAreDetectedByDomainNotByTextInTheUrl(): void
    {
        self::assertSame('x', CommunityCatalog::networkForUrl('https://mobile.twitter.com/user/status/42'));
        self::assertSame('threads', CommunityCatalog::networkForUrl('https://www.threads.com/@name/post/42'));
        self::assertSame('tiktok', CommunityCatalog::networkForUrl('https://www.tiktok.com/@name/video/42'));
        self::assertNull(CommunityCatalog::networkForUrl('https://x.com.example.org/status/42'));
        self::assertNull(CommunityCatalog::networkForUrl('https://example.org/?url=https://reddit.com'));
        self::assertNull(CommunityCatalog::networkForUrl('javascript:alert(1)'));
    }

    public function testCountsExcludeDemoStaleFutureDuplicateAndPressArticles(): void
    {
        $posts = [
            $this->post('#ZEVENT #ZEVENT Les dons', 'https://x.com/a/status/1'),
            $this->post('#zevent Les dons', 'https://www.reddit.com/r/france/comments/2'),
            $this->post('#zevent doublon', 'https://x.com/a/status/1'),
            $this->post('#zevent ancien', 'https://x.com/a/status/3', '2026-08-01'),
            $this->post('#zevent futur', 'https://x.com/a/status/4', '2026-10-01'),
            $this->post('#zevent exemple', 'https://x.com/a/status/5')->setDemo(true),
            $this->post('#zevent presse', 'https://x.com/a/status/6')->setSource((new Source())->setType('press')),
        ];
        $result = $this->summarize($posts);
        self::assertSame(2, $result['total']);
        self::assertSame(2, $result['trends']['#zevent']);
        self::assertSame(1, $result['counts']['x']);
        self::assertSame(1, $result['counts']['reddit']);
        self::assertSame(1, $result['sourceCounts']['x']);
        self::assertSame(1, $result['sourceCounts']['reddit']);
    }

    public function testCommunityQueryAndNetworkFiltersCombine(): void
    {
        $result = $this->summarize([
            $this->post('Gaming collecte de dons', 'https://x.com/a/status/1'),
            $this->post('ZEVENT collecte de dons', 'https://reddit.com/r/france/comments/2'),
            $this->post('Gaming concours', 'https://x.com/a/status/3'),
            $this->post('ZEVENT programme', 'https://x.com/a/status/4'),
        ], ['x'], 'gaming', 'dons');
        self::assertSame(1, $result['total']);
        self::assertSame('Gaming collecte de dons', $result['posts'][0]['article']->getTitle());
        self::assertSame([], $result['trends']);
    }

    public function testFacebookQueryIdentifiersAreNotCollapsed(): void
    {
        $result = $this->summarize([
            $this->post('ZEVENT', 'https://facebook.com/permalink.php?story_fbid=1&id=42'),
            $this->post('ZEVENT', 'https://facebook.com/permalink.php?story_fbid=2&id=42'),
        ]);
        self::assertSame(2, $result['total']);
    }

    public function testSuggestedTopicUsesReaderPreferencesAndIsStable(): void
    {
        $first = CommunityCatalog::suggestionFor(['Cybersécurité'], 'reader-a');
        self::assertContains($first, ['nouvelle cyberattaque', 'ransomware en France', 'arnaque par SMS']);
        self::assertSame($first, CommunityCatalog::suggestionFor(['Cybersécurité'], 'reader-a'));
    }
}
