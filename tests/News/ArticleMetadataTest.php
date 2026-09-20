<?php
namespace App\Tests\News;
use App\News\ArticleMetadata;
use PHPUnit\Framework\TestCase;
final class ArticleMetadataTest extends TestCase
{
    public function testPublisherDatesAreConvertedToUtcAndNotWebsiteDates(): void
    {
        $html = '<script type="application/ld+json">{"@graph":[{"@type":"WebSite","dateModified":"2020-01-01T00:00:00Z"},{"@type":"NewsArticle","datePublished":"2026-09-14T14:22:00+04:00","dateModified":"2026-09-14T14:23:00+04:00","timeRequired":"PT2M"}]}</script>';
        $data = ArticleMetadata::extract($html);
        self::assertSame('2026-09-14T10:22:00+00:00', $data['published']->format(DATE_ATOM));
        self::assertSame('2026-09-14T10:23:00+00:00', $data['modified']->format(DATE_ATOM));
        self::assertSame(2, $data['minutes']);
    }
    public function testMissingDatesAndUnknownTimezoneAreNotInvented(): void
    {
        self::assertSame([], ArticleMetadata::extract('<meta property="article:published_time" content="2026-09-14 14:22">'));
        self::assertSame([], ArticleMetadata::extract('<meta property="article:published_time" content="2026-02-30T14:22:00Z">'));
        $data = ArticleMetadata::extract('<meta property="article:published_time" content="2026-09-14T10:00:00Z"><meta property="article:modified_time" content="2026-09-13T10:00:00Z">');
        self::assertArrayNotHasKey('modified', $data);
        self::assertArrayNotHasKey('minutes', $data);
    }
}
