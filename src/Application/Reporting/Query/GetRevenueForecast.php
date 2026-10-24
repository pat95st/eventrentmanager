<?php

declare(strict_types=1);

namespace App\Application\Reporting\Query;

use App\Application\Query\QueryInterface;

/**
 * Umsatzprognose ab $fromYear über $yearCount Kalenderjahre (siehe
 * GetRevenueForecastHandler für die genaue Berechnungsgrundlage).
 */
final class GetRevenueForecast implements QueryInterface
{
    public function __construct(
        public readonly int $fromYear,
        public readonly int $yearCount = 5,
    ) {
    }
}
