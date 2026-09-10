<?php

namespace App\Tests\News;

use App\News\NewsBrief;
use App\News\ArticleEnrichment;
use PHPUnit\Framework\TestCase;

final class NewsBriefTest extends TestCase
{
    public function testCompleteSentencesWithoutDuplicatesOrTruncatedTail(): void
    {
        $html = '<p>La grève se poursuit.</p><p>La grève se poursuit.</p><p>Les agents demandent une négociation.</p><p>Les grévistes ont par ailleurs reçu, ce jeu…</p>';
        self::assertSame('La grève se poursuit. Les agents demandent une négociation.', NewsBrief::summarize($html));
    }

    public function testBudgetStopsAtSentenceBoundaryAndKeepsUncertainty(): void
    {
        self::assertSame('Une fuite reste une hypothèse.', NewsBrief::summarize('Une fuite reste une hypothèse. Le service est interrompu depuis mardi.', '', 8));
        self::assertSame('', NewsBrief::summarize('Le titre sans texte disponible', 'Le titre sans texte disponible'));
        self::assertSame('Deux alertes ont été signalées.', NewsBrief::summarize('<script>malicious();</script>Deux alertes ont été signalées.'));
    }

    public function testPublisherSuffixOnlyIsRemoved(): void
    {
        self::assertSame('Le Tampon : des services perturbés', NewsBrief::title('Le Tampon : des services perturbés - Linfo.re', 'Linfo.re'));
        self::assertSame('Linfo.re présente son nouveau site', NewsBrief::title('Linfo.re présente son nouveau site', 'Linfo.re'));
    }

    public function testExtractionExcludesRelatedContentAndPreservesFacts(): void
    {
        $html = '<nav>Menu.</nav><div class="article-content"><p>Les services municipaux sont perturbés depuis mardi soir.</p><p>Une fuite de données reste une hypothèse et aucune date de retour à la normale n’est annoncée.</p><aside><p>Autre actualité sans rapport.</p></aside></div><footer>Abonnez-vous.</footer>';
        $text = ArticleEnrichment::extract($html);
        self::assertStringContainsString('reste une hypothèse', $text);
        self::assertStringNotContainsString('Autre actualité', $text);
        self::assertStringNotContainsString('Abonnez-vous', $text);
    }

    public function testOnlyExpectedHttpsPublishersCanBeFetched(): void
    {
        self::assertTrue(ArticleEnrichment::allowed('https://www.linfo.re/la-reunion/article'));
        foreach (['http://www.linfo.re/test', 'https://127.0.0.1/test', 'https://www.linfo.re.evil.test/', 'https://user@www.linfo.re/', 'https://www.linfo.re:443/'] as $url) {
            self::assertFalse(ArticleEnrichment::allowed($url), $url);
        }
    }
}
