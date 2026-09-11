<?php
namespace App\Tests\News;

use App\News\LinfoSitemap;
use App\News\TrafficInfo;
use App\Entity\Article;
use App\Entity\Source;
use PHPUnit\Framework\TestCase;

final class DiscoveryTest extends TestCase
{
    public function testRoadClassification(): void
    {
        foreach (['Accidents sur la RN2', 'Choc frontal entre deux motos au Tampon', 'Embouteillages à Saint-Paul', 'Travaux : route fermée à Cilaos'] as $title) {
            self::assertTrue(TrafficInfo::isTraffic($title), $title);
        }
        foreach (['Accident d’avion en France', 'Accident domestique au Tampon', 'Le nouveau jeu vidéo arrive'] as $title) {
            self::assertFalse(TrafficInfo::isTraffic($title), $title);
        }
    }

    public function testSitemapRejectsOtherHostsAndForeignSections(): void
    {
        $xml = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">';
        foreach (['https://www.linfo.re/la-reunion/route','https://www.linfo.re/monde/route','https://evil.test/la-reunion/route'] as $url) {
            $xml .= '<url><loc>'.$url.'</loc><news:news><news:title>La route fermée</news:title><news:publication_date>2026-09-10T10:00:00Z</news:publication_date></news:news></url>';
        }
        $xml .= '</urlset>';
        self::assertCount(1, LinfoSitemap::items($xml));
        self::assertSame('https://www.linfo.re/la-reunion/route', LinfoSitemap::findLink($xml, 'La route fermée - Linfo.re'));
    }

    public function testLinfoLogoAndUnknownCause(): void
    {
        $a = (new Article())->setTitle('Accident sur la RN2 à Saint-Benoît')->setSource((new Source())->setName('Linfo.re'))
            ->setSourceUrl('https://news.google.com/rss/articles/example');
        self::assertSame('/images/publishers/linfo.png', $a->getPublisherLogo());
        self::assertStringContainsString('RN2', TrafficInfo::details($a)['where']);
        self::assertSame('Non précisée dans l’extrait disponible.', TrafficInfo::details($a)['cause']);
    }
}
