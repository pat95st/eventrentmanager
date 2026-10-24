<?php

declare(strict_types=1);

namespace App\Domain\LaborTime;

interface LaborTimeArticleRepositoryInterface
{
    public function save(LaborTimeArticle $laborTimeArticle): void;

    public function findById(string $id): ?LaborTimeArticle;

    /** @return LaborTimeArticle[] alphabetisch sortiert nach name */
    public function findAll(): array;

    public function existsByName(string $name, ?string $excludeId = null): bool;

    public function isUsedByOfferItems(string $id): bool;

    public function isUsedByInvoiceItems(string $id): bool;

    public function delete(string $id): void;
}
