<?php

namespace App\Tests\News;

use App\News\VerificationService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class VerificationServiceTest extends TestCase
{
    private function serviceWithBody(string $body, int $status = 200): VerificationService
    {
        $em = $this->createStub(\Doctrine\ORM\EntityManagerInterface::class);

        return new VerificationService($em, new MockHttpClient(new MockResponse($body, ['http_code' => $status])));
    }

    public function testCorroboratedPageVerifiesArticle(): void
    {
        $service = $this->serviceWithBody('<html><body><h1>Tour cycliste de La Réunion : le prologue à Saint-Denis</h1><p>78 coureurs au départ du prologue chronométré.</p></body></html>');

        $this->assertSame('verified', $service->check('https://example.com/tour', 'Tour cycliste de La Réunion : le prologue à Saint-Denis'));
    }

    public function testUnrelatedPageNeedsRecheck(): void
    {
        $service = $this->serviceWithBody('<html><body><h1>Recette de rougail saucisse</h1><p>Ingrédients et préparation.</p></body></html>');

        $this->assertSame('recheck', $service->check('https://example.com/rougail', 'Tour cycliste de La Réunion : le prologue à Saint-Denis'));
    }

    public function testMissingOrFailingPageIsUnreachable(): void
    {
        $service = $this->serviceWithBody('Introuvable', 404);

        $this->assertSame('unreachable', $service->check('https://example.com/absent', 'Un titre quelconque et sérieux'));
        $this->assertSame('unreachable', $service->check('pas-une-url', 'Un titre quelconque et sérieux'));
        $this->assertSame('unreachable', $service->check(null, 'Un titre quelconque et sérieux'));
    }

    public function testSocialSourceKeepsSocialLabel(): void
    {
        $service = $this->serviceWithBody('<html><body><h1>Quel budget pour vivre à Saint-Denis ?</h1><p>Bonjour, je déménage.</p></body></html>');
        $article = (new \App\Entity\Article())
            ->setTitle('Quel budget pour vivre à Saint-Denis ?')
            ->setSource((new \App\Entity\Source())->setName('Reddit r/reunion')->setSlug('reddit-test')->setType('social'));

        $this->assertSame('recheck', $service->verifyArticle($article, false));
        $this->assertFalse($article->isVerified());
        $this->assertSame('Réseau social', $article->getVerificationLabel());
    }

    public function testSignificantWordsSkipStopwords(): void
    {
        $words = VerificationService::significantWords('Le tour de La Réunion démarre à Saint-Denis');

        $this->assertContains('reunion', $words);
        $this->assertContains('saint', $words);
        $this->assertContains('denis', $words);
        $this->assertNotContains('le', $words);
        $this->assertNotContains('de', $words);
        $this->assertNotContains('la', $words);
    }

    public function testMatchingSourceDoesNotCertifyFacts(): void
    {
        $service = $this->serviceWithBody('<h1>Agriculture réunionnaise et biodiversité</h1>');
        $article = (new \App\Entity\Article())->setTitle('Agriculture réunionnaise et biodiversité')
            ->setSourceUrl('https://example.com/agriculture');
        self::assertSame('verified', $service->verifyArticle($article, false));
        self::assertFalse($article->isVerified());
        self::assertSame('Source retrouvée', $article->getTrustLabel());
    }
}
