<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Domain\Exception\ValidationException;
use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Settings\RentalReminderSettings;
use App\Domain\Settings\RentalReminderSettingsRepositoryInterface;

/**
 * Nur leichte Validierung, siehe UpdateInvoiceSettingsHandler für das
 * identische Muster: beide Vorläufe müssen positive Ganzzahlen sein. Nach
 * oben auf 720 Stunden (30 Tage) begrenzt — ein größerer Vorlauf wäre kein
 * sinnvoller Anwendungsfall mehr und deutet eher auf einen Tippfehler hin.
 */
final class UpdateRentalReminderSettingsHandler implements CommandHandlerInterface
{
    private const MAX_LEAD_HOURS = 720;

    public function __construct(private readonly RentalReminderSettingsRepositoryInterface $settingsRepository)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateRentalReminderSettings);

        $errors = [];

        $pickupLeadHours = $this->parseLeadHours($command->pickupLeadHours, 'pickupLeadHours', $errors);
        $returnLeadHours = $this->parseLeadHours($command->returnLeadHours, 'returnLeadHours', $errors);
        $checklistLeadHours = $this->parseLeadHours($command->checklistLeadHours, 'checklistLeadHours', $errors);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $this->settingsRepository->save(new RentalReminderSettings(
            pickupLeadHours: $pickupLeadHours,
            returnLeadHours: $returnLeadHours,
            checklistLeadHours: $checklistLeadHours,
            checklistAutoSendEnabled: $command->checklistAutoSendEnabled,
        ));

        return null;
    }

    /** @param array<string, string> $errors */
    private function parseLeadHours(string $value, string $field, array &$errors): int
    {
        $parsed = filter_var(trim($value), FILTER_VALIDATE_INT);
        if ($parsed === false || $parsed < 1 || $parsed > self::MAX_LEAD_HOURS) {
            $errors[$field] = 'Bitte einen Vorlauf zwischen 1 und ' . self::MAX_LEAD_HOURS . ' Stunden angeben.';

            return 0;
        }

        return $parsed;
    }
}
