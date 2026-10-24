<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\Settings\Command\UpdateInvoiceDueReminderSettings;
use App\Application\Settings\Query\GetInvoiceDueReminderSettings;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use function App\Infrastructure\Http\t;

/**
 * Einstellungsseite für den Vorlauf der automatischen Fälligkeits-Erinnerung
 * (siehe App\Domain\Settings\InvoiceDueReminderSettings und
 * bin/send-invoice-due-reminders.php) — analog RentalReminderSettingsController.
 */
final class InvoiceDueReminderSettingsController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function edit(Request $request): void
    {
        $settings = $this->queries->ask(new GetInvoiceDueReminderSettings());

        echo View::page('settings/invoice-due-reminders', [
            'settings' => $settings,
            'errors' => [],
            'old' => [],
        ], activeNav: 'settings-rechnungserinnerungen', title: t('settings.invoice_due_reminders.title'));
    }

    public function update(Request $request): void
    {
        $command = new UpdateInvoiceDueReminderSettings(
            enabled: $request->input('enabled') === '1',
            leadDays: $request->input('lead_days', '') ?? '',
        );

        try {
            $this->commands->dispatch($command);
            Flash::success(t('settings.invoice_due_reminders.flash.saved'));
            Response::redirect('/einstellungen/rechnungserinnerungen');
        } catch (ValidationException $e) {
            $settings = $this->queries->ask(new GetInvoiceDueReminderSettings());
            echo View::page('settings/invoice-due-reminders', [
                'settings' => $settings,
                'errors' => $e->errors(),
                'old' => $request->post,
            ], activeNav: 'settings-rechnungserinnerungen', title: t('settings.invoice_due_reminders.title'));
        }
    }
}
