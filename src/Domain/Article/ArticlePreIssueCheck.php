<?php

declare(strict_types=1);

namespace App\Domain\Article;

use DateTimeImmutable;

/**
 * Ein Vor-Ausgabe-Check zu einem Artikel (z. B. "Default Show bei
 * Digitalpult geladen") — ein frei definierbarer Prüfpunkt, der VOR der
 * Ausgabe einer Inventarnummer dieses Artikels einmal je Vermietungsposten
 * bestätigt werden muss (siehe RentalItem::$preIssueChecksConfirmed,
 * ConfirmRentalItemPreIssueChecksHandler). Analog ArticleAccessory, aber
 * ohne Anzahl — es handelt sich um eine durchzuführende Prüfung, kein
 * mitzugebendes Teil.
 */
final class ArticlePreIssueCheck
{
    public function __construct(
        public readonly string $id,
        public readonly string $articleId,
        public string $description,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }
}
