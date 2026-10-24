<?php

declare(strict_types=1);

namespace App\Application\Dunning\Command;

use App\Domain\Dunning\DunningFeeType;
use App\Domain\Dunning\DunningLevelRepositoryInterface;

/**
 * Gemeinsame Validierung für Create-/UpdateDunningLevelHandler (bewusst als
 * eigener kleiner Baustein statt Code-Duplikation zwischen beiden Handlern,
 * da hier — anders als sonst im Projekt üblich — mehrere Felder mit
 * gegenseitiger Abhängigkeit geprüft werden, siehe $feeType/$feeValue).
 */
final class ValidatesDunningLevelInput
{
    /**
     * @return array{0: string, 1: int, 2: DunningFeeType, 3: ?string, 4: array<string, string>}
     */
    public static function validate(
        string $rawName,
        string $rawDaysAfterDue,
        string $rawFeeType,
        string $rawFeeValue,
        DunningLevelRepositoryInterface $dunningLevels,
        ?string $excludeId = null,
    ): array {
        $errors = [];

        $name = trim($rawName);
        if ($name === '') {
            $errors['name'] = 'Bitte einen Namen angeben.';
        }

        $daysAfterDueValue = filter_var(trim($rawDaysAfterDue), FILTER_VALIDATE_INT);
        $daysAfterDue = 0;
        if ($daysAfterDueValue === false || $daysAfterDueValue < 0) {
            $errors['daysAfterDue'] = 'Bitte eine gültige Anzahl Tage (0 oder mehr) angeben.';
        } else {
            $daysAfterDue = $daysAfterDueValue;
            if ($dunningLevels->existsByDaysAfterDue($daysAfterDue, $excludeId)) {
                $errors['daysAfterDue'] = 'Es existiert bereits eine Mahnstufe mit dieser Anzahl Tage nach Fälligkeit.';
            }
        }

        $feeType = DunningFeeType::tryFrom($rawFeeType);
        $feeValue = null;
        if ($feeType === null) {
            $errors['feeType'] = 'Bitte eine gültige Gebührenart wählen.';
        } elseif ($feeType !== DunningFeeType::None) {
            $feeValueRaw = filter_var(str_replace(',', '.', trim($rawFeeValue)), FILTER_VALIDATE_FLOAT);
            if ($feeValueRaw === false || $feeValueRaw < 0) {
                $errors['feeValue'] = 'Bitte einen gültigen Gebührenwert (0 oder mehr) angeben.';
            } else {
                $feeValue = number_format($feeValueRaw, 2, '.', '');
            }
        }

        return [$name, $daysAfterDue, $feeType ?? DunningFeeType::None, $feeValue, $errors];
    }
}
