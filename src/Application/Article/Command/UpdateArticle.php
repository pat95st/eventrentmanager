<?php

declare(strict_types=1);

namespace App\Application\Article\Command;

use App\Application\Command\CommandInterface;

final class UpdateArticle implements CommandInterface
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $manufacturerId,
        public readonly string $prefix,
        public readonly ?string $categoryId,
        public readonly ?string $description,
        public readonly ?string $weightValue,
        public readonly ?string $weightUnit,
        public readonly ?string $dimensions,
        public readonly ?string $purchasePrice = null,
        public readonly bool $requiresElectricalInspection = false,
        public readonly ?string $protectionClass = null,
        public readonly bool $isBillable = true,
        public readonly ?string $dailyRentalPrice = null,
        public readonly bool $is19Inch = false,
        public readonly ?string $heightUnits = null,
        public readonly ?string $installDepthCm = null,
        public readonly bool $hasFirmware = false,
    ) {
    }
}
