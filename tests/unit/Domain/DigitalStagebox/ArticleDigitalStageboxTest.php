<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\DigitalStagebox;

use App\Domain\DigitalStagebox\ArticleDigitalStagebox;
use Codeception\Test\Unit;

final class ArticleDigitalStageboxTest extends Unit
{
    public function testLabelsForCombinedConnectors(): void
    {
        $stagebox = new ArticleDigitalStagebox('a', 'p', 24, 8, 12, 4);

        $this->assertSame('32 (24 × XLR, 8 × Kombi)', $stagebox->inputsLabel());
        $this->assertSame('16 (12 × analog, 4 × AES/EBU)', $stagebox->outputsLabel());
        $this->assertSame('32/16', $stagebox->channelSummary());
    }

    public function testLabelsForSingleConnectorTypeAndNone(): void
    {
        $stagebox = new ArticleDigitalStagebox('a', 'p', 0, 16, 0, 0);

        $this->assertSame('16 × Kombi', $stagebox->inputsLabel());
        $this->assertSame('keine', $stagebox->outputsLabel());
    }
}
