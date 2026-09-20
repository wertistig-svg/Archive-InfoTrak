<?php
namespace App\Tests\News;

use App\InfoTrak\VideoEmbed;
use App\News\NewsBrief;
use App\News\VideoDiscovery;
use PHPUnit\Framework\TestCase;

final class VideoDiscoveryTest extends TestCase
{
    public function testArticleVideoAndJsonLd(): void
    {
        self::assertSame('https://www.youtube.com/embed/abc12345678', VideoDiscovery::find('<article><iframe src="https://www.youtube.com/embed/abc12345678"></iframe></article>', 'https://imazpress.com/a'));
        self::assertSame('https://imazpress.com/reportage.mp4', VideoDiscovery::find('<script type="application/ld+json">{"@graph":[{"@type":"VideoObject","contentUrl":"/reportage.mp4"}]}</script>', 'https://imazpress.com/a'));
    }
    public function testDailymotionAndUnsupportedPlayers(): void
    {
        self::assertSame('https://www.dailymotion.com/embed/video/x123abc', VideoEmbed::parse('https://geo.dailymotion.com/player/test.html?video=x123abc')['embedUrl']);
        self::assertNull(VideoDiscovery::find('<article><iframe src="https://unknown.test/player"></iframe></article>', 'https://imazpress.com/a'));
        self::assertNull(VideoDiscovery::find('<iframe src="https://www.youtube.com/embed/abc12345678"></iframe>', 'https://imazpress.com/a'));
    }
    public function testVideoLabelIsNotASummary(): void
    {
        self::assertSame('', NewsBrief::summarize('VIDÉO. Un incendie ravage des maisons'));
        self::assertSame('Les secours sont sur place.', NewsBrief::summarize('VIDÉO. Les secours sont sur place.'));
    }
}
