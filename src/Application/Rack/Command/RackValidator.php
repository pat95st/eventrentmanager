<?php

declare(strict_types=1);

namespace App\Application\Rack\Command;

use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;
use App\Domain\Rack\Rack;
use App\Domain\Rack\RackRailType;
use App\Domain\Rack\RackRepositoryInterface;
use App\Domain\Rack\RackSlot;
use App\Domain\Rack\RackWidth;
use DateTimeImmutable;

/**
 * Prüfungen für Racks (Migration 115):
 * - Stammdaten: Bezeichnung, HE 1–60, Einbautiefe optional (> 0), Hersteller
 *   optional aus der Herstellerliste. Die Inventarnummer wird automatisch
 *   vergeben (RACK-0001 …, Migration 120).
 * - Feste Zuordnung: Geräte-Exemplar muss existieren und darf keinem anderen
 *   Rack fest zugeordnet sein.
 * - Einbau: nur Exemplare von 19-Zoll-Artikeln, HE-Bereich innerhalb des
 *   Racks und frei, Gerät steckt nicht schon in einem Rack, Einbautiefe des
 *   Geräts passt (wenn bei beiden gepflegt).
 */
final class RackValidator
{
    public function __construct(
        private readonly RackRepositoryInterface $racks,
        private readonly ManufacturerRepositoryInterface $manufacturers,
        private readonly InventoryNumberRepositoryInterface $inventoryNumbers,
        private readonly ArticleRepositoryInterface $articles,
    ) {
    }

    /** @param string $inventoryNumber bei neuen Racks die vergebene Nummer (RACK-0001 …), bei bestehenden ignoriert */
    public function build(string $id, RackInput $input, ?Rack $existing, string $inventoryNumber = ''): Rack
    {
        $errors = [];
        $name = trim($input->name);
        if ($name === '') {
            $errors['name'] = 'Bitte eine Bezeichnung angeben.';
        }

        $manufacturerId = $this->blank($input->manufacturerId);
        if ($manufacturerId !== null && $this->manufacturers->findById($manufacturerId) === null) {
            $errors['manufacturer_id'] = 'Der gewählte Hersteller existiert nicht.';
        }

        $units = filter_var(trim((string) $input->heightUnits), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 60]]);
        if ($units === false) {
            $errors['height_units'] = 'Bitte die Anzahl Höheneinheiten angeben (1 bis 60).';
            $units = 1;
        }
        if ($existing !== null && $units !== false) {
            foreach ($this->racks->findSlots($existing->id) as $slot) {
                if ($this->slotEnd($slot) > $units) {
                    $errors['height_units'] = 'Es sind Geräte bis HE ' . $this->slotEnd($slot) . ' eingebaut — das Rack kann nicht kleiner werden.';
                    break;
                }
            }
        }

        $depth = $this->decimal($input->installDepthCm);
        if ($depth === false) {
            $errors['install_depth_cm'] = 'Bitte eine gültige Einbautiefe in cm angeben (z. B. 45 oder 45,5).';
            $depth = null;
        }

        $railType = RackRailType::tryFrom((string) $input->railType);
        if ($railType === null) {
            $errors['rail_type'] = 'Bitte die Rackschienen auswählen.';
        }
        $width = RackWidth::tryFrom((string) $input->width);
        if ($width === null) {
            $errors['width'] = 'Bitte die Breite auswählen.';
        }

        $fixedId = $this->blank($input->fixedInventoryNumberId);
        if ($fixedId !== null) {
            if ($this->inventoryNumbers->findById($fixedId) === null) {
                $errors['fixed_inventory_number_id'] = 'Das gewählte Geräte-Exemplar existiert nicht.';
            } else {
                $other = $this->racks->findByFixedInventoryNumber($fixedId);
                if ($other !== null && $other->id !== $existing?->id) {
                    $errors['fixed_inventory_number_id'] = "Dieses Gerät ist bereits dem Rack „{$other->name}“ fest zugeordnet.";
                }
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $now = new DateTimeImmutable();
        $color = $this->blank($input->color);
        $description = $this->blank($input->description);

        if ($existing !== null) {
            $existing->name = $name;
            $existing->manufacturerId = $manufacturerId;
            $existing->heightUnits = $units;
            $existing->installDepthCm = $depth;
            $existing->railType = $railType;
            $existing->width = $width;
            $existing->color = $color;
            $existing->hasShockMount = $input->hasShockMount;
            $existing->hasWheels = $input->hasWheels;
            $existing->fixedInventoryNumberId = $fixedId;
            $existing->description = $description;
            $existing->updatedAt = $now;

            return $existing;
        }

        return new Rack($id, $name, $inventoryNumber, $manufacturerId, $units, $depth, $railType, $width, $color, $input->hasShockMount, $input->hasWheels, $fixedId, $description, $now, $now);
    }

    /** Prüft einen Einbau und liefert die Startposition. */
    public function validateSlot(Rack $rack, string $inventoryNumberId, ?string $rawStartUnit): int
    {
        $inventoryNumber = $this->inventoryNumbers->findById($inventoryNumberId);
        if ($inventoryNumber === null) {
            throw new ValidationException(['slot' => 'Bitte ein Gerät auswählen.']);
        }
        $article = $this->articles->findById($inventoryNumber->articleId);
        if ($article === null || !$article->is19Inch) {
            throw new ValidationException(['slot' => 'Nur Geräte von 19-Zoll-Artikeln können eingebaut werden.']);
        }

        $existingSlot = $this->racks->findSlotByInventoryNumber($inventoryNumberId);
        if ($existingSlot !== null) {
            $other = $this->racks->findById($existingSlot->rackId);
            throw new ValidationException(['slot' => "Das Gerät {$inventoryNumber->number} ist bereits im Rack „" . ($other?->name ?? '?') . '“ eingebaut.']);
        }

        $height = max(1, (int) ($article->heightUnits ?? 1));
        $start = filter_var(trim((string) $rawStartUnit), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($start === false) {
            $start = $this->firstFreeUnit($rack, $height);
            if ($start === null) {
                throw new ValidationException(['slot' => "Im Rack ist kein freier Bereich mit {$height} HE mehr."]);
            }
        }
        $end = $start + $height - 1;
        if ($end > $rack->heightUnits) {
            throw new ValidationException(['slot' => "Das Gerät belegt {$height} HE und passt ab HE {$start} nicht mehr ins Rack ({$rack->heightUnits} HE)."]);
        }
        foreach ($this->racks->findSlots($rack->id) as $slot) {
            if ($start <= $this->slotEnd($slot) && $end >= $slot->startUnit) {
                throw new ValidationException(['slot' => "HE {$start}–{$end} ist bereits belegt."]);
            }
        }

        if ($rack->installDepthCm !== null && $article->installDepthCm !== null && (float) $article->installDepthCm > (float) $rack->installDepthCm) {
            throw new ValidationException(['slot' => 'Das Gerät ist mit ' . $this->formatCm($article->installDepthCm) . ' cm tiefer als die Einbautiefe des Racks (' . $this->formatCm($rack->installDepthCm) . ' cm).']);
        }

        return $start;
    }

    /** Letzte belegte HE eines Einbaus (Höhe aus dem Artikel, mindestens 1). */
    public function slotEnd(RackSlot $slot): int
    {
        $inventoryNumber = $this->inventoryNumbers->findById($slot->inventoryNumberId);
        $article = $inventoryNumber !== null ? $this->articles->findById($inventoryNumber->articleId) : null;

        return $slot->startUnit + max(1, (int) ($article?->heightUnits ?? 1)) - 1;
    }

    private function firstFreeUnit(Rack $rack, int $height): ?int
    {
        $occupied = [];
        foreach ($this->racks->findSlots($rack->id) as $slot) {
            for ($u = $slot->startUnit; $u <= $this->slotEnd($slot); $u++) {
                $occupied[$u] = true;
            }
        }
        for ($start = 1; $start + $height - 1 <= $rack->heightUnits; $start++) {
            $free = true;
            for ($u = $start; $u < $start + $height; $u++) {
                if (isset($occupied[$u])) {
                    $free = false;
                    break;
                }
            }
            if ($free) {
                return $start;
            }
        }

        return null;
    }

    private function blank(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        return $value === '' ? null : $value;
    }

    private function decimal(?string $raw): string|false|null
    {
        $raw = $this->blank($raw);
        if ($raw === null) {
            return null;
        }
        $normalized = str_replace(',', '.', $raw);
        if (!is_numeric($normalized) || (float) $normalized <= 0 || (float) $normalized > 99999) {
            return false;
        }

        return number_format((float) $normalized, 1, '.', '');
    }

    private function formatCm(string $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 1, ',', ''), '0'), ',');
    }
}
