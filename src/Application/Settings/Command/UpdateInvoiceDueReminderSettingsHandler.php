<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\InvoiceDueReminderSettings;
use App\Domain\Settings\InvoiceDueReminderSettingsRepositoryInterface;

/**
 * Nur leichte Validierung, siehe UpdateRentalReminderSettingsHandler für das
 * identische Muster: der Vorlauf muss eine positive Ganzzahl sein. Nach oben
 * auf 90 Tage begrenzt — ein größerer Vorlauf wäre kein sinnvoller
 * Anwendungsfall mehr und deutet eher auf einen Tippfehler hin.
 */
final class UpdateInvoiceDueReminderSettingsHandler implements CommandHandlerInterface
{
    private const MAX_LEAD_DAYS = 90;

    public function __construct(private readonly InvoiceDueReminderSettingsRepositoryInterface $settingsRepository)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateInvoiceDueReminderSettings);

        $errors = [];

        $leadDays = filter_var(trim($command->leadDays), FILTER_VALIDATE_INT);
        if ($leadDays === false || $leadDays < 1 || $leadDays > self::MAX_LEAD_DAYS) {
            $errors['leadDays'] = 'Bitte einen Vorlauf zwischen 1 und ' . self::MAX_LEAD_DAYS . ' Tagen angeben.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $this->settingsRepository->save(new InvoiceDueReminderSettings(
            enabled: $command->enabled,
            leadDays: $leadDays,
        ));

        return null;
    }
}
