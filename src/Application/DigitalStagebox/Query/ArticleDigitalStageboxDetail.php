<?php

declare(strict_types=1);

namespace App\Application\DigitalStagebox\Query;

use App\Domain\DigitalStagebox\ArticleDigitalStagebox;
use App\Domain\DigitalStagebox\DigitalProtocol;

final class ArticleDigitalStageboxDetail
{
    public function __construct(
        public readonly ArticleDigitalStagebox $stagebox,
        public readonly ?DigitalProtocol $protocol,
    ) {
    }
}
