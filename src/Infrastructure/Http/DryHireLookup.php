<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Domain\DryHire\DryHireRepositoryInterface;
use App\Domain\DryHire\DryHireRepositoryInterface as Repo;

/**
 * Zugriff der Views auf Dry-Hire-Daten (Migration 141), analog zu
 * CompanyBranding/UiFonts – wird in bootstrap.php gesetzt. Ohne Repository
 * (z. B. Tests) liefert alles „kein Dry-Hire“.
 */
final class DryHireLookup
{
    private static ?DryHireRepositoryInterface $repo = null;

    public static function set(?DryHireRepositoryInterface $repo): void
    {
        self::$repo = $repo;
    }

    public static function repository(): ?DryHireRepositoryInterface
    {
        return self::$repo;
    }

    public static function articlePrice(?string $articleId): ?string
    {
        return $articleId !== null ? self::$repo?->articlePrice($articleId) : null;
    }

    public static function isCustomerDryHire(?string $customerId): bool
    {
        return $customerId !== null && $customerId !== '' && (self::$repo?->isCustomerDryHire($customerId) ?? false);
    }

    /**
     * Daten für die Dokumentformulare (Angebot, AB, Rechnung).
     *
     * @return array{tiers: list<array{from: int, pct: float}>, customers: list<string>, prices: array<string, string>, checked: bool, available: bool}
     */
    public static function formData(string $documentType, ?string $documentId, ?string $customerId): array
    {
        $repo = self::$repo;
        if ($repo === null) {
            return ['tiers' => [], 'customers' => [], 'prices' => [], 'checked' => false, 'available' => false];
        }
        $flag = $documentId !== null ? $repo->documentFlag($documentType, $documentId) : null;

        return [
            'tiers' => array_map(static fn ($t) => ['from' => $t->fromDay, 'pct' => (float) $t->percentage], $repo->tiers()),
            'customers' => $repo->dryHireCustomerIds(),
            'prices' => $repo->articlePrices(),
            'checked' => $flag ?? self::isCustomerDryHire($customerId),
            'available' => true,
        ];
    }

    public static function documentFlag(string $type, string $id): ?bool
    {
        return self::$repo?->documentFlag($type, $id);
    }

    /** @internal Alias für Typ-Konstanten */
    public const OFFER = Repo::DOC_OFFER;
    public const ORDER_CONFIRMATION = Repo::DOC_ORDER_CONFIRMATION;
    public const INVOICE = Repo::DOC_INVOICE;
}
