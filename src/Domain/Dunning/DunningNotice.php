<?php

declare(strict_types=1);

namespace App\Domain\Dunning;

use DateTimeImmutable;

/**
 * Eine konkrete, für eine überfällige Rechnung erstellte Mahnung. Analog zur
 * Rechnungsnummer (siehe DocumentNumberSequence) erhält jede Mahnung beim
 * Erstellen eine fortlaufende Mahnungsnummer (ebenfalls über DocumentNumberSequence).
 *
 * Die Stufen-Angaben ($levelName/$daysAfterDueSnapshot/$feeType/$feeValue)
 * UND der zum Erstellungszeitpunkt offene Rechnungsbetrag ($invoiceGrossTotal)
 * werden vollständig auf die Mahnung gesnapshotet (Snapshot-Prinzip, siehe
 * DunningLevel-Klassendoc) — weder eine spätere Änderung der Mahnstufe noch
 * (theoretisch) der Rechnung selbst verändert eine bereits erstellte Mahnung
 * rückwirkend. $feeAmount ist der daraus berechnete, bereits in Euro
 * ausgedrückte Gebührenbetrag (0.00 bei feeType=None).
 */
final class DunningNotice
{
    public function __construct(
        public readonly string $id,
        public readonly string $invoiceId,
        public readonly string $dunningNumber,
        public ?string $dunningLevelId, // Kann NULL werden, wenn die Stufe später gelöscht wird (ON DELETE SET NULL) — Snapshot-Felder bleiben davon unberührt.
        public readonly string $levelName,
        public readonly int $daysAfterDueSnapshot,
        public readonly DunningFeeType $feeType,
        public readonly ?string $feeValue,
        public readonly string $feeAmount,
        public readonly string $invoiceGrossTotal,
        public readonly DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $sentAt,
        public readonly string $createdBy, // 'manual' oder 'automatic', siehe DunningController/bin/send-dunning-notices.php
    ) {
    }

    /** Gesamtsumme aus offenem Rechnungsbetrag zzgl. Mahngebühr. */
    public function totalDue(): float
    {
        return (float) $this->invoiceGrossTotal + (float) $this->feeAmount;
    }
}
