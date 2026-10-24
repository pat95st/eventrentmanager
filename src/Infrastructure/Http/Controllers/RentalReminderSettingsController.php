<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\Settings\Command\UpdateRentalReminderSettings;
use App\Application\Settings\Query\GetRentalReminderSettings;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use function App\Infrastructure\Http\t;

/**
 * Einstellungsseite für den Vorlauf der automatischen Abhol-/Rückgabe-
 * Erinnerungen (siehe App\Domain\Settings\RentalReminderSettings und
 * bin/send-rental-reminders.php).
 */
final class RentalReminderSettingsController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function edit(Request $request): void
    {
        $settings = $this->queries->ask(new GetRentalReminderSettings());

        echo View::page('settings/rental-reminders', [
            'settings' => $settings,
            'errors' => [],
            'old' => [],
        ], activeNav: 'settings-erinnerungen', title: t('settings.rental_reminders.title'));
    }

    public function update(Request $request): void
    {
        $command = new UpdateRentalReminderSettings(
            pickupLeadHours: $request->input('pickup_lead_hours', '') ?? '',
            returnLeadHours: $request->input('return_lead_hours', '') ?? '',
            checklistLeadHours: $request->input('checklist_lead_hours', '') ?? '',
            checklistAutoSendEnabled: $request->input('checklist_auto_send_enabled') === '1',
        );

        try {
            $this->commands->dispatch($command);
            Flash::success(t('settings.rental_reminders.flash.saved'));
            Response::redirect('/einstellungen/erinnerungen');
        } catch (ValidationException $e) {
            $settings = $this->queries->ask(new GetRentalReminderSettings());
            echo View::page('settings/rental-reminders', [
                'settings' => $settings,
                'errors' => $e->errors(),
                'old' => $request->post,
            ], activeNav: 'settings-erinnerungen', title: t('settings.rental_reminders.title'));
        }
    }
}
