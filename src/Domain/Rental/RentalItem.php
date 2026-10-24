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
    ) {
    }
}
