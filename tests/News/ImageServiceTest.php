<?php

namespace App\Tests\News;

use App\News\ImageService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class ImageServiceTest extends TestCase
{
    public function testFindsOgImage(): void
    {
        $html = '<html><head><meta property="og:image" content="https://example.com/photo.jpg" /></head></html>';

        $this->assertSame('https://example.com/photo.jpg', ImageService::findImage($html, 'https://example.com/article'));
    }

    public function testResolvesRelativeImageAgainstPage(): void
    {
        $html = '<html><head><meta name="twitter:image" content="/medias/une.jpg" /></head></html>';

        $this->assertSame('https://example.com/medias/une.jpg', ImageService::findImage($html, 'https://example.com/article/1'));
    }

    public function testIgnoresDataUrisAndMissingTags(): void
    {
        $this->assertNull(ImageService::findImage('<html><head><meta property="og:image" content="data:image/png;base64,xx" /></head></html>', 'https://example.com/a'));
        $this->assertNull(ImageService::findImage('<html><head><title>Sans image</title></head></html>', 'https://example.com/a'));
    }

    public function testExtractUsesHttpClient(): void
    {
        $html = '<html><head><meta property="og:image" content="https://example.com/une.jpg" /></head></html>';
        $service = new ImageService(new MockHttpClient(new MockResponse($html)));

        $this->assertSame('https://example.com/une.jpg', $service->extractImageUrl('https://example.com/article'));
    }

    public function testExtractRejectsBadUrls(): void
    {
        $service = new ImageService(new MockHttpClient(new MockResponse('ok')));

        $this->assertNull($service->extractImageUrl(null));
        $this->assertNull($service->extractImageUrl('pas-une-url'));
    }
}
