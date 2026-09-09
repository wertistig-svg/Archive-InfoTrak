<?php

namespace App\Tests\News;

use App\News\NewsImportService;
use PHPUnit\Framework\TestCase;

class LinkExtractionTest extends TestCase
{
    public function testAggregatorDescriptionYieldsPublisherLink(): void
    {
        $description = '<table><tr><td><a href="https://www.zinfos974.com/article-reunion">Lire</a></td></tr></table>';
        $fallback = 'https://news.google.com/articles/xyz';

        $this->assertSame('https://www.zinfos974.com/article-reunion', NewsImportService::extractDirectLink($description, $fallback));
    }

    public function testGoogleLinksAreSkipped(): void
    {
        $description = '<a href="https://news.google.com/articles/xyz">Lire</a>';
        $fallback = 'https://news.google.com/rss/articles/xyz';

        $this->assertSame($fallback, NewsImportService::extractDirectLink($description, $fallback));
    }

    public function testNoLinkKeepsFallback(): void
    {
        $this->assertSame('https://example.com/x', NewsImportService::extractDirectLink('<p>Sans lien.</p>', 'https://example.com/x'));
    }
}
