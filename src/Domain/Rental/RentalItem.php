<?php

declare(strict_types=1);

namespace App\Domain\Rental;

use DateTimeImmutable;

/**
 * Ein geplanter Artikel innerhalb einer Vermietung, auf Artikel-Ebene mit
 * Menge (nicht an eine konkrete Inventarnummer gebunden) — die tatsächliche
 * Ausgabe konkreter Inventarnummern erfolgt separat über RentalIssuedItem.
 */
final class RentalItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $rentalId,
        public readonly string $articleId,
        public int $quantity,
        // Bei Artikeln mit Zubehör: wurde die Vollständigkeit des Zubehörs bei
        // der Ausgabe geprüft (siehe ConfirmRentalItemAccessoriesHandler)?
        // Bei Artikeln ohne Zubehör bleibt dies ohne Bedeutung.
        public bool $accessoryChecked,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        // Bei Artikeln mit Vor-Ausgabe-Checks (siehe ArticlePreIssueCheck, z. B.
        // "Default Show bei Digitalpult geladen"): wurden diese vor der Ausgabe
        // bestätigt (siehe ConfirmRentalItemPreIssueChecksHandler)? Bei Artikeln
        // ohne Vor-Ausgabe-Checks bleibt dies ohne Bedeutung — exakt analog
        // $accessoryChecked, bewusst ein eigenes, unabhängiges Feld. Default
        // false (ans Ende gestellt, analog $accessoryFlatRateEnabled in Invoice
        // für dasselbe Nachrüst-Muster bei bestehenden Konstruktor-Aufrufen).
        public bool $preIssueChecksConfirmed = false,
        // Migration 177: tours only — pieces that may be issued on top of the
        // base quantity because single stops need more (kept up to date by
        // App\Application\Tour\TourMaterialSync, never written by save()).
        public int $tourExtraQuantity = 0,
    ) {
    }

    /** Pieces that may be issued in total (base quantity plus tour extras). */
    public function issueQuantity(): int
    {
        return $this->quantity + $this->tourExtraQuantity;
    }
}
