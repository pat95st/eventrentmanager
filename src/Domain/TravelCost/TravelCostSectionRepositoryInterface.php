<?php

declare(strict_types=1);

namespace App\Domain\TravelCost;

interface TravelCostSectionRepositoryInterface
{
    public function findFor(TravelCostDocumentType $type, string $documentId): ?TravelCostSection;

    /** Stores the section with all its lines (the lines replace the stored ones). */
    public function save(TravelCostSection $section): void;

    public function deleteFor(TravelCostDocumentType $type, string $documentId): void;
}
