<?php

declare(strict_types=1);

namespace App\Application\Rental\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\Mail\EmailTemplateRenderer;
use App\Application\Rental\Query\GetRental;
use App\Application\Rental\Query\GetRentalHandler;
use App\Application\Rental\Query\RentalDetail;
use App\Domain\Exception\ValidationException;
use App\Domain\Mail\EmailTemplateKey;
use App\Domain\Mail\MailCategory;
use App\Domain\Warehouse\WarehouseRepositoryInterface;
use App\Infrastructure\Mail\MailerInterface;

use function App\Infrastructure\Http\e;

/**
 * Verschickt die Abhol-Erinnerung für eine Vermietung per E-Mail (siehe
 * bin/send-rental-reminders.php) — analog EmailPackingListPdfHandler, aber
 * OHNE PDF-Anhang: die Erinnerung ist eine reine Text-Mail.
 */
final class EmailRentalPickupReminderHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly GetRentalHandler $getRentalHandler,
        private readonly WarehouseRepositoryInterface $warehouses,
        private readonly MailerInterface $mailer,
        private readonly EmailTemplateRenderer $templateRenderer,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof EmailRentalPickupReminder);

        $recipient = trim($command->recipientEmail);
        if ($recipient === '' || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            throw new ValidationException(['recipient_email' => 'Bitte eine gültige E-Mail-Adresse angeben.']);
        }

        // Wirft NotFoundException, falls die Vermietung nicht existiert.
        $detail = $this->getRentalHandler->handle(new GetRental($command->rentalId));

        $variables = $this->buildTemplateVariables($detail);
        $rendered = $this->templateRenderer->render(EmailTemplateKey::RentalPickupReminder, $variables);

        $attachments = [];
        if ($rendered->logoAttachment !== null) {
            $attachments[] = $rendered->logoAttachment;
        }

        $this->mailer->send(
            $recipient,
            $rendered->subject,
            $rendered->textBody,
            $attachments,
            $rendered->htmlBody,
            MailCategory::RentalPickupReminder,
        );

        return null;
    }

    /** @return array<string, string> */
    private function buildTemplateVariables(RentalDetail $detail): array
    {
        $rental = $detail->rental;

        $warehouseLine = '';
        if ($rental->pickupWarehouseId !== null) {
            $warehouse = $this->warehouses->findById($rental->pickupWarehouseId);
            if ($warehouse !== null) {
                $warehouseLine = 'Abholort: ' . e($warehouse->name) . ', ' . e($warehouse->street) . ', ' . e($warehouse->postalCode) . ' ' . e($warehouse->city) . '<br>';
            }
        }

        return [
            'customer_name' => e($detail->customer->displayName()),
            'event_name' => e($rental->eventName),
            'pickup_at' => $rental->pickupAt?->format('d.m.Y, H:i \U\h\r') ?? '',
            'warehouse_line' => $warehouseLine,
        ];
    }
}
