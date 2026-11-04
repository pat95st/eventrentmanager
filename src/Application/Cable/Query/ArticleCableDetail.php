<?php

declare(strict_types=1);

namespace App\Application\Cable\Query;

use App\Domain\Cable\ArticleCable;
use App\Domain\Cable\CableType;
use App\Domain\Cable\ConnectorType;

final class ArticleCableDetail
{
    public function __construct(
        public readonly ArticleCable $cable,
        public readonly ?CableType $type,
        public readonly ?ConnectorType $connectorA,
        public readonly ?ConnectorType $connectorB,
    ) {
    }
}
