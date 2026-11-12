<?php

declare(strict_types=1);

namespace App\Application\Insurance\Query;

use App\Domain\Insurance\InsuranceField;

/**
 * Eine Zeile der Versicherungsliste = ein physisches Exemplar. Für bereits
 * ausgegebene Posten mit Inventarnummer, für noch nicht ausgegebene
 * (nur geplante) Stückzahlen ohne Inventarnummer. Fehlende Werte sind null
 * und bleiben im Export leer.
 */
final class InsuranceListRow
{
    public function __construct(
        public readonly string $articleId,
        public readonly ?string $inventoryNumberId,
        public readonly ?string $category,
        public readonly string $manufacturerModel,
        public readonly ?string $inventoryNumber,
        public readonly ?int $acquisitionYear,
        public readonly ?string $currentValue,
        public readonly ?string $currentValueOrigin, // siehe CurrentValueResolver::ORIGIN_*
        public readonly ?string $purchasePrice,
        public readonly ?string $currentValueNote = null, // Begründung einer KI-Schätzung
    ) {
    }

    public function isIssued(): bool
    {
        return $this->inventoryNumberId !== null;
    }

    /** Rohwert eines Feldes (Beträge als "1234.50", Jahr als "2021"), null = leer. */
    public function value(InsuranceField $field): ?string
    {
        return match ($field) {
            InsuranceField::Category => $this->category,
            InsuranceField::ManufacturerModel => $this->manufacturerModel,
            InsuranceField::InventoryNumber => $this->inventoryNumber,
            InsuranceField::AcquisitionYear => $this->acquisitionYear !== null ? (string) $this->acquisitionYear : null,
            InsuranceField::CurrentValue => $this->currentValue,
            InsuranceField::PurchasePrice => $this->purchasePrice,
        };
    }
}
