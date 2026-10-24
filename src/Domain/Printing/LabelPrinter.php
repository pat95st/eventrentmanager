<?php

declare(strict_types=1);

namespace App\Domain\Printing;

use DateTimeImmutable;

/**
 * Ein im Admin-Bereich hinterlegter Etikettendrucker (z. B. ein Brother
 * P-touch im Lager). $driverType verweist auf den passenden Treiber in
 * App\Infrastructure\Printing\LabelPrinterDriverRegistry — neue Modelle
 * bekommen einen neuen driverType-Wert plus eine neue Treiberklasse, ohne
 * dass sich an diesem Entity irgendetwas ändern muss.
 */
final class LabelPrinter
{
    public function __construct(
        public readonly string $id,
        public string $name,
        public string $driverType,
        public string $ipAddress,
        public int $port,
        public int $tapeWidthMm,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }
}
