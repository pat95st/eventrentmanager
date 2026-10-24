<?php

declare(strict_types=1);

namespace App\Infrastructure\Printing;

/**
 * Abstraktion über ein konkretes Etikettendrucker-Modell. Ein neues Modell
 * hinzuzufügen bedeutet: eine neue Klasse, die dieses Interface implementiert,
 * plus einen Eintrag in LabelPrinterDriverRegistry — an Application- oder
 * Controller-Code ändert sich dafür nichts.
 */
interface LabelPrinterDriverInterface
{
    /** Erzeugt die rohen, druckfertigen Bytes (bereits im geräteeigenen Protokoll) für dieses Etikett. */
    public function renderLabel(LabelContent $content, PrinterProfile $profile): string;

    /** Erzeugt und versendet (über den Transport) ein kleines Testetikett zur Verbindungs-/Funktionsprüfung. */
    public function sendTestPrint(PrinterProfile $profile): void;

    /** @return int[] von diesem Treiber unterstützte Bandbreiten in mm, z. B. [12, 24, 29]. */
    public function getSupportedTapeWidths(): array;
}
