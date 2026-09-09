<?php

namespace App\Tests\InfoTrak;

use App\InfoTrak\VideoEmbed;
use PHPUnit\Framework\TestCase;

class VideoEmbedTest extends TestCase
{
    public function testDirectMp4File(): void
    {
        $parsed = VideoEmbed::parse('https://example.com/videos/reportage.mp4');

        $this->assertNotNull($parsed);
        $this->assertSame('file', $parsed['type']);
        $this->assertSame('https://example.com/videos/reportage.mp4', $parsed['embedUrl']);
    }

    public function testYoutubeWatchUrlUsesNocookieEmbed(): void
    {
        $parsed = VideoEmbed::parse('https://www.youtube.com/watch?v=aqz-KE-bpKQ');

        $this->assertNotNull($parsed);
        $this->assertSame('youtube', $parsed['type']);
        $this->assertSame('https://www.youtube-nocookie.com/embed/aqz-KE-bpKQ?rel=0', $parsed['embedUrl']);
    }

    public function testYoutubeShortAndShortsUrls(): void
    {
        $this->assertSame('youtube', VideoEmbed::parse('https://youtu.be/aqz-KE-bpKQ')['type']);
        $this->assertSame('youtube', VideoEmbed::parse('https://www.youtube.com/shorts/aqz-KE-bpKQ')['type']);
        $this->assertSame('youtube', VideoEmbed::parse('https://www.youtube.com/embed/aqz-KE-bpKQ')['type']);
    }

    public function testVimeoUrl(): void
    {
        $parsed = VideoEmbed::parse('https://vimeo.com/123456789');

        $this->assertNotNull($parsed);
        $this->assertSame('vimeo', $parsed['type']);
        $this->assertStringContainsString('player.vimeo.com/video/123456789', $parsed['embedUrl']);
    }

    public function testUnsupportedOrInvalidUrlsReturnNull(): void
    {
        $this->assertNull(VideoEmbed::parse(null));
        $this->assertNull(VideoEmbed::parse(''));
        $this->assertNull(VideoEmbed::parse('not-a-url'));
        $this->assertNull(VideoEmbed::parse('https://example.com/article-sans-video'));
        // Fausse URL YouTube sans identifiant valide.
        $this->assertNull(VideoEmbed::parse('https://www.youtube.com/watch?v=!!!'));
    }
}
