<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\PriceTier;
use App\Domain\Settings\PriceTierRepositoryInterface;
use App\Shared\Money;

/**
 * Tag 1 ist per Definition immer 100 % (= der hinterlegte Preis pro
 * Einsatztag eines Artikels selbst) und bleibt fest — nur die Aufschläge für
 * Tag 2 bis $command->maxDays sind über die Oberfläche änderbar. Die Anzahl
 * der Staffel-Tage selbst ist ebenfalls über die Oberfläche einstellbar
 * (weniger Tage als zuvor entfernt die überzähligen Einträge, mehr Tage legt
 * neue an), begrenzt durch PriceTier::MAX_DAY_COUNT.
 */
final class UpdatePriceTiersHandler implements CommandHandlerInterface
{
    private const MIN_DAYS = 2;

    public function __construct(private readonly PriceTierRepositoryInterface $priceTiers)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdatePriceTiers);

        $errors = [];

        if ($command->maxDays < self::MIN_DAYS || $command->maxDays > PriceTier::MAX_DAY_COUNT) {
            $errors['max_days'] = 'Bitte eine Anzahl Staffel-Tage zwischen ' . self::MIN_DAYS . ' und ' . PriceTier::MAX_DAY_COUNT . ' angeben.';

            throw new ValidationException($errors);
        }

        $normalized = [];

        for ($day = self::MIN_DAYS; $day <= $command->maxDays; $day++) {
            $raw = $command->percentages[$day] ?? null;
            $value = Money::normalize($raw);

            if ($value === false || $value === null) {
                $errors["percentage_{$day}"] = "Bitte einen gültigen Prozentwert für {$day} Einsatztage angeben (z. B. 175 oder 175,00).";

                continue;
            }

            if ((float) $value <= 0) {
                $errors["percentage_{$day}"] = "Der Prozentwert für {$day} Einsatztage muss größer als 0 sein.";

                continue;
            }

            $normalized[$day] = $value;
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $tiers = [new PriceTier(dayCount: 1, percentage: '100.00')];
        foreach ($normalized as $day => $value) {
            $tiers[] = new PriceTier(dayCount: $day, percentage: $value);
        }

        $this->priceTiers->saveAll($tiers);

        return null;
    }
}
