<?php

declare(strict_types=1);

namespace App\Domain\Insurance;

/**
 * Wählbare Spalten der Versicherungsliste (CSV-/PDF-Export einer
 * Veranstaltung). Reihenfolge der Cases = Spaltenreihenfolge im Export.
 */
enum InsuranceField: string
{
    case Category = 'category';
    case ManufacturerModel = 'manufacturer_model';
    case InventoryNumber = 'inventory_number';
    case AcquisitionYear = 'acquisition_year';
    case CurrentValue = 'current_value';
    case PurchasePrice = 'purchase_price';

    public function label(): string
    {
        return match ($this) {
            self::Category => 'Artikelkategorie',
            self::ManufacturerModel => 'Hersteller und Modell',
            self::InventoryNumber => 'Inventarnummer',
            self::AcquisitionYear => 'Anschaffungsjahr',
            self::CurrentValue => 'Geschätzter Zeitwert',
            self::PurchasePrice => 'Anschaffungspreis',
        };
    }

    public function isAmount(): bool
    {
        return $this === self::CurrentValue || $this === self::PurchasePrice;
    }

    /**
     * Wandelt eine (Formular-/Einstellungs-)Liste in Felder um: unbekannte
     * Werte und Duplikate fallen weg, die Reihenfolge folgt immer den Cases.
     *
     * @param iterable<string> $values
     * @return list<self>
     */
    public static function fromList(iterable $values): array
    {
        $wanted = [];
        foreach ($values as $value) {
            $field = self::tryFrom(trim((string) $value));
            if ($field !== null) {
                $wanted[$field->value] = true;
            }
        }

        return array_values(array_filter(self::cases(), static fn (self $f) => isset($wanted[$f->value])));
    }
}
