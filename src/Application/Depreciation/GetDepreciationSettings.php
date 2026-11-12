<?php

declare(strict_types=1);

namespace App\Application\Depreciation;

use App\Application\Query\QueryInterface;

/** Ergebnis: array{settings: DepreciationSettings, categoryYears: array<string, int>}. */
final class GetDepreciationSettings implements QueryInterface
{
}
