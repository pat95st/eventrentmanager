<?php

declare(strict_types=1);

namespace App\Domain\OrderConfirmation;

interface OrderConfirmationRepositoryInterface
{
    public function save(OrderConfirmation $orderConfirmation): void;

    public function findById(string $id): ?OrderConfirmation;

    /** @return OrderConfirmation[] sortiert nach Erstellungsdatum absteigend, höchstens $limit Zeilen ab $offset */
    public function search(?string $searchTerm, ?OrderConfirmationStatus $status, int $limit, int $offset): array;

    public function countSearch(?string $searchTerm, ?OrderConfirmationStatus $status): int;

    public function delete(string $id): void;

    /** @return OrderConfirmationItem[] sortiert nach sortOrder */
    public function findItemsByOrderConfirmation(string $orderConfirmationId): array;

    /** @param OrderConfirmationItem[] $items */
    public function replaceItems(string $orderConfirmationId, array $items): void;

    /** @return OrderConfirmationFreeTextItem[] sortiert nach sortOrder */
    public function findFreeTextItemsByOrderConfirmation(string $orderConfirmationId): array;

    /** @param OrderConfirmationFreeTextItem[] $items */
    public function replaceFreeTextItems(string $orderConfirmationId, array $items): void;

    /** @return OrderConfirmationSectionText[] sortiert nach sortOrder */
    public function findSectionTextsByOrderConfirmation(string $orderConfirmationId): array;

    /** @param OrderConfirmationSectionText[] $sectionTexts */
    public function replaceSectionTexts(string $orderConfirmationId, array $sectionTexts): void;

    /**
     * Alle nicht stornierten Auftragsbestätigungen (Draft/Sent/Confirmed) —
     * Grundlage für die Umsatzprognose (siehe GetRevenueForecastHandler),
     * eingeordnet nach ihrem Veranstaltungszeitraum
     * (OrderConfirmation::$startDate).
     *
     * @return OrderConfirmation[]
     */
    public function findNotCancelledForRevenueForecast(): array;

    /**
     * IDs aller Angebote, die bereits in eine Auftragsbestätigung
     * übernommen wurden (OrderConfirmation::$sourceOfferId, siehe
     * CreateOrderConfirmationFromOfferHandler) — verhindert bei der
     * Umsatzprognose eine Doppelzählung desselben Geschäfts.
     *
     * @return string[]
     */
    public function findSourceOfferIds(): array;
}
