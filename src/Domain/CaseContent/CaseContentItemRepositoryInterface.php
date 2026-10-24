<?php

declare(strict_types=1);

namespace App\Domain\CaseContent;

interface CaseContentItemRepositoryInterface
{
    public function save(CaseContentItem $item): void;

    public function findById(string $id): ?CaseContentItem;

    /** @return CaseContentItem[] */
    public function findByCaseInventoryNumber(string $caseInventoryNumberId): array;

    public function existsForCaseAndArticle(string $caseInventoryNumberId, string $articleId, ?string $excludeId = null): bool;

    /** Wird für die Löschsperre bei Artikeln benötigt (Artikel ist noch in einem Case gepackt). */
    public function isUsedByArticle(string $articleId): bool;

    public function delete(string $id): void;
}
