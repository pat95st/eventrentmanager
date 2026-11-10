<?php

declare(strict_types=1);

namespace App\Domain\DryHire;

interface DryHireRepositoryInterface
{
    public const DOC_OFFER = 'offer';
    public const DOC_ORDER_CONFIRMATION = 'order_confirmation';
    public const DOC_INVOICE = 'invoice';

    /** @return array<string, string> article_id => Dry-Hire-Tagesmietsatz */
    public function articlePrices(): array;

    public function articlePrice(string $articleId): ?string;

    public function setArticlePrice(string $articleId, ?string $dailyPrice): void;

    public function isCustomerDryHire(string $customerId): bool;

    /** @return list<string> */
    public function dryHireCustomerIds(): array;

    public function setCustomerDryHire(string $customerId, bool $dryHire): void;

    /** @return DryHireTier[] sortiert nach fromDay */
    public function tiers(): array;

    /** @param DryHireTier[] $tiers */
    public function saveTiers(array $tiers): void;

    public function documentFlag(string $type, string $id): ?bool;

    public function setDocumentFlag(string $type, string $id, bool $enabled): void;
}
