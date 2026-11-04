<?php

declare(strict_types=1);

namespace App\Application\Rack\Command;

use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;
use App\Domain\Rack\Rack;
use App\Domain\Rack\RackExemplar;
use App\Domain\Rack\RackRailType;
use App\Domain\Rack\RackRepositoryInterface;
use App\Domain\Rack\RackSlot;
use App\Domain\Rack\RackWidth;
use App\Shared\Prefix;
use DateTimeImmutable;

/**
 * Prüfungen für Racks (Migration 115):
 * - Rack-Typ: Bezeichnung, Hersteller (Pflicht), Kürzel (Pflicht, je
 *   Hersteller eindeutig), HE 1–60, Einbautiefe optional (> 0).
 *   Inventur- und Rack-Nummern bekommen die Exemplare (Migration 124).
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

    public function build(string $id, RackInput $input, ?Rack $existing): Rack
    {
        $errors = [];
        $name = trim($input->name);
        if ($name === '') {
            $errors['name'] = 'Bitte eine Bezeichnung angeben.';
        }

        // Hersteller und Kürzel bilden die Inventurnummern der Exemplare (wie bei Artikeln).
        $manufacturerId = $this->blank($input->manufacturerId);
        if ($manufacturerId === null) {
            $errors['manufacturer_id'] = 'Bitte einen Hersteller auswählen (für selbst gebaute Racks „Generisch“).';
        } elseif ($this->manufacturers->findById($manufacturerId) === null) {
            $errors['manufacturer_id'] = 'Der gewählte Hersteller existiert nicht.';
        }

        $prefix = Prefix::normalize($input->prefix);
        if ($prefix === '') {
            $errors['prefix'] = 'Bitte ein Kürzel angeben.';
        } elseif (!Prefix::isValid($prefix)) {
            $errors['prefix'] = 'Das Kürzel darf nur aus Großbuchstaben und Ziffern bestehen (max. 10 Zeichen).';
        } elseif ($manufacturerId !== null && $this->racks->existsByPrefix($manufacturerId, $prefix, $existing?->id)) {
            $errors['prefix'] = 'Dieses Kürzel wird bei diesem Hersteller bereits von einem anderen Rack-Typ verwendet.';
        }

        $units = filter_var(trim((string) $input->heightUnits), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 60]]);
        if ($units === false) {
            $errors['height_units'] = 'Bitte die Anzahl Höheneinheiten angeben (1 bis 60).';
            $units = 1;
        }
        if ($existing !== null && $units !== false) {
            foreach ($this->racks->findExemplars($existing->id) as $exemplar) {
                foreach ($this->racks->findSlots($exemplar->id) as $slot) {
                    if ($this->slotEnd($slot) > $units) {
                        $errors['height_units'] = 'In ' . $exemplar->rackNumber . ' sind Geräte bis HE ' . $this->slotEnd($slot) . ' eingebaut — der Rack-Typ kann nicht kleiner werden.';
                        break 2;
                    }
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

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $now = new DateTimeImmutable();
        $color = $this->blank($input->color);
        $description = $this->blank($input->description);

        if ($existing !== null) {
            $existing->name = $name;
            $existing->prefix = $prefix;
            $existing->manufacturerId = $manufacturerId;
            $existing->heightUnits = $units;
            $existing->installDepthCm = $depth;
            $existing->railType = $railType;
            $existing->width = $width;
            $existing->color = $color;
            $existing->hasShockMount = $input->hasShockMount;
            $existing->hasWheels = $input->hasWheels;
            $existing->description = $description;
            $existing->updatedAt = $now;

            return $existing;
        }

        return new Rack($id, $name, $prefix, $manufacturerId, $units, $depth, $railType, $width, $color, $input->hasShockMount, $input->hasWheels, $description, $now, $now);
    }

    /** Prüft einen Einbau und liefert die Startposition. */
    public function validateSlot(Rack $rack, RackExemplar $exemplar, string $inventoryNumberId, ?string $rawStartUnit): int
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
            $other = $this->racks->findExemplarById($existingSlot->exemplarId);
            throw new ValidationException(['slot' => "Das Gerät {$inventoryNumber->number} ist bereits in Rack " . ($other?->rackNumber ?? '?') . ' eingebaut.']);
        }

        $height = max(1, (int) ($article->heightUnits ?? 1));
        $start = filter_var(trim((string) $rawStartUnit), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($start === false) {
            $start = $this->firstFreeUnit($rack, $exemplar, $height);
            if ($start === null) {
                throw new ValidationException(['slot' => "Im Rack ist kein freier Bereich mit {$height} HE mehr."]);
            }
        }
        $end = $start + $height - 1;
        if ($end > $rack->heightUnits) {
            throw new ValidationException(['slot' => "Das Gerät belegt {$height} HE und passt ab HE {$start} nicht mehr ins Rack ({$rack->heightUnits} HE)."]);
        }
        foreach ($this->racks->findSlots($exemplar->id) as $slot) {
            if ($start <= $this->slotEnd($slot) && $end >= $slot->startUnit) {
                throw new ValidationException(['slot' => "HE {$start}–{$end} ist bereits belegt."]);
            }
        }

        if ($rack->installDepthCm !== null && $article->installDepthCm !== null && (float) $article->installDepthCm > (float) $rack->installDepthCm) {
            throw new ValidationException(['slot' => 'Das Gerät ist mit ' . $this->formatCm($article->installDepthCm) . ' cm tiefer als die Einbautiefe des Racks (' . $this->formatCm($rack->installDepthCm) . ' cm).']);
        }

        return $start;
    }

    /** Prüft die feste Gerätezuordnung eines Exemplars; liefert die ID oder null. */
    public function validateFixedDevice(?string $rawInventoryNumberId, ?RackExemplar $exemplar): ?string
    {
        $fixedId = $this->blank($rawInventoryNumberId);
        if ($fixedId === null) {
            return null;
        }
        if ($this->inventoryNumbers->findById($fixedId) === null) {
            throw new ValidationException(['fixed_inventory_number_id' => 'Das gewählte Geräte-Exemplar existiert nicht.']);
        }
        $other = $this->racks->findExemplarByFixedInventoryNumber($fixedId);
        if ($other !== null && $other->id !== $exemplar?->id) {
            throw new ValidationException(['fixed_inventory_number_id' => "Dieses Gerät ist bereits Rack {$other->rackNumber} fest zugeordnet."]);
        }

        return $fixedId;
    }

    /** Letzte belegte HE eines Einbaus (Höhe aus dem Artikel, mindestens 1). */
    public function slotEnd(RackSlot $slot): int
    {
        $inventoryNumber = $this->inventoryNumbers->findById($slot->inventoryNumberId);
        $article = $inventoryNumber !== null ? $this->articles->findById($inventoryNumber->articleId) : null;

        return $slot->startUnit + max(1, (int) ($article?->heightUnits ?? 1)) - 1;
    }

    private function firstFreeUnit(Rack $rack, RackExemplar $exemplar, int $height): ?int
    {
        $occupied = [];
        foreach ($this->racks->findSlots($exemplar->id) as $slot) {
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
