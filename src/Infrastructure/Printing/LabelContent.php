<?php

declare(strict_types=1);

namespace App\Infrastructure\Printing;

/**
 * Generischer Inhalt eines zu druckenden Etiketts — bewusst NICHT an
 * "Inventarnummer" gebunden, damit dieselbe Struktur später auch für andere
 * Etikettenarten (z. B. Case-Kennzeichnung) wiederverwendet werden kann. Die
 * eigentliche fachliche Zusammenstellung (was auf das Etikett soll) passiert
 * in der jeweiligen Application-Handler (siehe
 * App\Application\Printing\Command\PrintInventoryNumberLabelHandler), nicht
 * hier oder im Treiber.
 */
final class LabelContent
{
    /**
     * @param array{type: string, value: string}|null $barcode Barcode-Angabe,
     *        z. B. ['type' => 'code128', 'value' => 'BOS-00123']. Aktuell
     *        unterstützt nur BrotherPtouchRasterDriver den Typ "code128".
     * @param string[] $lines Textzeilen, die zusätzlich zum Barcode gedruckt werden
     *        (z. B. die Inventarnummer selbst als für Menschen lesbarer Text).
     */
    public function __construct(
        public readonly ?array $barcode,
        public readonly array $lines,
    ) {
    }
}
