<?php

declare(strict_types=1);

namespace App\Application\Depreciation;

use App\Domain\Depreciation\DepreciationAsset;
use App\Domain\Depreciation\DepreciationSchedule;

final class DepreciationReportRow
{
    public function __construct(
        public readonly DepreciationAsset $asset,
        public readonly DepreciationSchedule $schedule,
    ) {
    }
}
