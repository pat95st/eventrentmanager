<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\TravelCost\TravelCostDocumentType;
use App\Domain\TravelCost\TravelCostSection;
use App\Domain\TravelCost\TravelCostSectionRepositoryInterface;

final class InMemoryTravelCostSectionRepository implements TravelCostSectionRepositoryInterface
{
    /** @var array<string, TravelCostSection> */
    public array $sections = [];

    public function findFor(TravelCostDocumentType $type, string $documentId): ?TravelCostSection
    {
        return $this->sections[$type->value . ':' . $documentId] ?? null;
    }

    public function save(TravelCostSection $section): void
    {
        $this->sections[$section->documentType->value . ':' . $section->documentId] = $section;
    }

    public function deleteFor(TravelCostDocumentType $type, string $documentId): void
    {
        unset($this->sections[$type->value . ':' . $documentId]);
    }
}
