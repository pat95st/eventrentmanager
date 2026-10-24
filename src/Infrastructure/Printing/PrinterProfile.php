<?php

declare(strict_types=1);

namespace App\Infrastructure\Printing;

use App\Domain\Printing\LabelPrinter;

/**
 * Read-only Sicht auf einen konfigurierten Drucker, wie sie ein
 * LabelPrinterDriverInterface benötigt (Verbindungsdaten + Treiber-Konfig).
 * Entkoppelt die Infrastructure/Printing-Schicht bewusst vom
 * Domain\Printing\LabelPrinter-Entity, damit ein Treiber nicht von der
 * Persistenzschicht/Domain-Struktur abhängen muss.
 */
final class PrinterProfile
{
    public function __construct(
        public readonly string $name,
        public readonly string $driverType,
        public readonly string $ipAddress,
        public readonly int $port,
        public readonly int $tapeWidthMm,
    ) {
    }

    public static function fromLabelPrinter(LabelPrinter $printer): self
    {
        return new self(
            name: $printer->name,
            driverType: $printer->driverType,
            ipAddress: $printer->ipAddress,
            port: $printer->port,
            tapeWidthMm: $printer->tapeWidthMm,
        );
    }
}
