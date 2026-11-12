<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Insurance\CurrentValueRepositoryInterface;
use App\Domain\Insurance\InsuranceField;

final class InMemoryCurrentValueRepository implements CurrentValueRepositoryInterface
{
    /** @var array<string, string> */
    public array $fixedValues = [];
    /** @var list<InsuranceField> */
    public array $fields = [];

    public function articleFixedValue(string $articleId): ?string
    {
        return $this->fixedValues[$articleId] ?? null;
    }

    public function articleFixedValues(array $articleIds): array
    {
        return array_intersect_key($this->fixedValues, array_flip($articleIds));
    }

    public function setArticleFixedValue(string $articleId, ?string $value): void
    {
        if ($value === null) {
            unset($this->fixedValues[$articleId]);

            return;
        }
        $this->fixedValues[$articleId] = $value;
    }

    public function exportFields(): array
    {
        return $this->fields !== [] ? $this->fields : InsuranceField::cases();
    }

    public function saveExportFields(array $fields): void
    {
        $this->fields = $fields;
    }
}
