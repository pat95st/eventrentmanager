<?php

declare(strict_types=1);

namespace App\Domain\Dunning;

use DateTimeImmutable;

/**
 * Eine frei konfigurierbare Mahnstufe (z. B. "Zahlungserinnerung" nach 3
 * Tagen ohne Gebühr, "1. Mahnung" nach 14 Tagen mit 5,00 € Festgebühr,
 * "2. Mahnung" nach 28 Tagen mit 2 % vom Rechnungsbetrag). Die Stufen werden
 * anhand von $daysAfterDue sortiert (siehe DunningLevelRepositoryInterface::findAllOrdered())
 * — beim manuellen Erstellen einer Mahnung (siehe CreateDunningNoticeHandler)
 * wählt der Anwender die passende Stufe selbst aus, beim automatischen
 * Versand (siehe bin/send-dunning-notices.php) wird stattdessen automatisch
 * die höchste Stufe ermittelt, deren $daysAfterDue die tatsächliche Anzahl
 * an Verzugstagen nicht überschreitet.
 *
 * Beim Erstellen einer konkreten Mahnung (DunningNotice) werden Name,
 * Fristtage und Gebühr auf die Mahnung SELBST gesnapshotet (Snapshot-Prinzip,
 * wie bei LaborTimeArticle -> OfferItem/InvoiceItem) — eine spätere Änderung
 * oder Löschung dieser Stufe wirkt sich also NIE auf bereits erstellte
 * Mahnungen aus.
 */
final class DunningLevel
{
    public function __construct(
        public readonly string $id,
        public string $name,
        public int $daysAfterDue,
        public DunningFeeType $feeType,
        public ?string $feeValue, // Bedeutung abhängig von $feeType: Festbetrag in EUR oder Prozentsatz; NULL bei feeType=None
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }
}
