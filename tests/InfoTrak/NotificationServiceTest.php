<?php

namespace App\Tests\InfoTrak;

use App\Service\NotificationService;
use PHPUnit\Framework\TestCase;

class NotificationServiceTest extends TestCase
{
    public function testSummarizeKeepsShortText(): void
    {
        $this->assertSame('Texte court.', NotificationService::summarize('  Texte court.  '));
    }

    public function testSummarizeCutsOnWordBoundary(): void
    {
        $summary = NotificationService::summarize(str_repeat('mot ', 100), 160);

        $this->assertLessThanOrEqual(161, mb_strlen($summary));
        $this->assertStringEndsWith('…', $summary);
        $this->assertStringNotContainsString('  ', $summary);
    }

    public function testSummarizeCollapsesWhitespace(): void
    {
        $this->assertSame('Un deux trois.', NotificationService::summarize("Un\n  deux\t trois."));
    }
}
