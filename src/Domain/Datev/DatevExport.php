<?php

declare(strict_types=1);

namespace App\Domain\Datev;

use DateTimeImmutable;

/**
 * Protokolleintrag eines DATEV-Exports. Dient der Nachvollziehbarkeit
 * (welcher Zeitraum wurde wann an wen übermittelt) und verhindert, dass der
 * automatische Versand denselben Zeitraum mehrfach verschickt (siehe
 * DatevExportRepositoryInterface::existsAutoSentForPeriod()).
 */
final class DatevExport
{
    public function __construct(
        public readonly string $id,
        public readonly DateTimeImmutable $periodFrom,
        public readonly DateTimeImmutable $periodTo,
        public readonly DatevExportTrigger $trigger,
        public readonly ?string $recipient,
        public readonly int $bookingCount,
        public readonly int $documentCount,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }
}
