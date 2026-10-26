<?php

declare(strict_types=1);

namespace App\Application\Manufacturer\Query;

use App\Application\Query\QueryInterface;

/**
 * $search/$limit bleiben optional (Default: alle, alphabetisch, wie bisher
 * — siehe z. B. ArticleController::index(), das weiterhin ohne Parameter
 * aufruft, um die volle Liste für das Filter-Dropdown zu erhalten). Mit
 * beiden gefüllt: kleine Treffermenge für die neue Live-Suche
 * (ManufacturerController::search()).
 */
final class ListManufacturers implements QueryInterface
{
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?int $limit = null,
    ) {
    }
}
