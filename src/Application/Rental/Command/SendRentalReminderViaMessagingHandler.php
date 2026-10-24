<?php

declare(strict_types=1);

namespace App\Application\Rental\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\Messaging\MessagingSendResult;
use App\Application\Messaging\MessagingTemplateRenderer;
use App\Application\Rental\Query\GetRental;
use App\Application\Rental\Query\GetRentalHandler;
use App\Domain\Customer\Customer;
use App\Domain\Messaging\MessageTemplateKey;
use App\Domain\Messaging\MessagingChannel;
use App\Domain\Messaging\MessagingSettingsRepositoryInterface;
use App\Domain\Rental\Rental;
use App\Domain\Rental\RentalReminderType;
use App\Domain\Warehouse\WarehouseRepositoryInterface;
use App\Infrastructure\Messaging\MessageSenderException;
use App\Infrastructure\Messaging\MessageSenderInterface;
use InvalidArgumentException;

/**
 * Verschickt die Abhol-/Rückgabe-Erinnerung für eine Vermietung ZUSÄTZLICH
 * über jeden aktivierten UND konfigurierten Messenger, für den der Kunde
 * die nötigen Kontaktdaten hinterlegt hat (siehe bin/send-rental-reminders.php,
 * das diesen Befehl neben EmailRentalPickupReminder/EmailRentalReturnReminder
 * aufruft). WhatsApp/Signal nutzen Customer::$phone, Telegram
 * Customer::$telegramChatId.
 *
 * Jeder Kanal wird EINZELN versucht und fängt seinen eigenen Fehler ab —
 * ein fehlgeschlagener Kanal (z. B. falsches Telegram-Token) soll die
 * anderen nicht verhindern. Der Aufrufer bekommt über MessagingSendResult
 * zurück, was geklappt hat und was nicht.
 *
 * NICHT unterstützt: RentalReminderType::CustomerChecklist — die
 * Kunden-Checkliste bleibt vorerst E-Mail-only (siehe Projektdoku,
 * "Bekannte offene Punkte").
 */
final class SendRentalReminderViaMessagingHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly GetRentalHandler $getRentalHandler,
        private readonly WarehouseRepositoryInterface $warehouses,
        private readonly MessagingSettingsRepositoryInterface $messagingSettingsRepository,
        private readonly MessagingTemplateRenderer $templateRenderer,
        private readonly MessageSenderInterface $sender,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof SendRentalReminderViaMessaging);

        $templateKey = match ($command->type) {
            RentalReminderType::Pickup => MessageTemplateKey::RentalPickupReminder,
            RentalReminderType::Return => MessageTemplateKey::RentalReturnReminder,
            RentalReminderType::CustomerChecklist => throw new InvalidArgumentException('Messenger-Versand für die Kunden-Checkliste wird nicht unterstützt.'),
        };

        // Wirft NotFoundException, falls die Vermietung nicht existiert.
        $detail = $this->getRentalHandler->handle(new GetRental($command->rentalId));
        $rental = $detail->rental;
        $customer = $detail->customer;

        $settings = $this->messagingSettingsRepository->get();
        $variables = $this->buildVariables($command->type, $rental, $customer);

        $sent = [];
        $failed = [];

        if ($settings->isChannelReady(MessagingChannel::WhatsApp) && $this->hasPhone($customer)) {
            try {
                $resolved = $this->templateRenderer->resolveWhatsappParameters($templateKey, $variables);
                if ($resolved['templateName'] === '') {
                    throw new MessageSenderException('Für WhatsApp ist noch keine Vorlage für "' . $templateKey->label() . '" hinterlegt.');
                }
                $this->sender->sendWhatsappTemplateMessage((string) $customer->phone, $resolved['templateName'], $resolved['languageCode'], $resolved['parameters']);
                $sent[] = MessagingChannel::WhatsApp;
            } catch (MessageSenderException $e) {
                $failed[MessagingChannel::WhatsApp->value] = $e->getMessage();
            }
        }

        if ($settings->isChannelReady(MessagingChannel::Signal) && $this->hasPhone($customer)) {
            try {
                $text = $this->templateRenderer->renderPlainText(MessagingChannel::Signal, $templateKey, $variables);
                $this->sender->sendSignalMessage((string) $customer->phone, $text);
                $sent[] = MessagingChannel::Signal;
            } catch (MessageSenderException $e) {
                $failed[MessagingChannel::Signal->value] = $e->getMessage();
            }
        }

        if ($settings->isChannelReady(MessagingChannel::Telegram) && $this->hasTelegramChatId($customer)) {
            try {
                $text = $this->templateRenderer->renderPlainText(MessagingChannel::Telegram, $templateKey, $variables);
                $this->sender->sendTelegramMessage((string) $customer->telegramChatId, $text);
                $sent[] = MessagingChannel::Telegram;
            } catch (MessageSenderException $e) {
                $failed[MessagingChannel::Telegram->value] = $e->getMessage();
            }
        }

        return new MessagingSendResult($sent, $failed);
    }

    private function hasPhone(Customer $customer): bool
    {
        return $customer->phone !== null && trim($customer->phone) !== '';
    }

    private function hasTelegramChatId(Customer $customer): bool
    {
        return $customer->telegramChatId !== null && trim($customer->telegramChatId) !== '';
    }

    /** @return array<string, string> */
    private function buildVariables(RentalReminderType $type, Rental $rental, Customer $customer): array
    {
        $warehouseId = $type === RentalReminderType::Pickup ? $rental->pickupWarehouseId : $rental->returnWarehouseId;
        $warehouseLine = '';

        if ($warehouseId !== null) {
            $warehouse = $this->warehouses->findById($warehouseId);
            if ($warehouse !== null) {
                $label = $type === RentalReminderType::Pickup ? 'Abholort' : 'Rückgabeort';
                $warehouseLine = $label . ': ' . $warehouse->name . ', ' . $warehouse->street . ', ' . $warehouse->postalCode . ' ' . $warehouse->city;
            }
        }

        $variables = [
            'customer_name' => $customer->displayName(),
            'event_name' => $rental->eventName,
            'warehouse_line' => $warehouseLine,
        ];

        if ($type === RentalReminderType::Pickup) {
            $variables['pickup_at'] = $rental->pickupAt?->format('d.m.Y, H:i \U\h\r') ?? '';
        } else {
            $variables['return_at'] = $rental->returnAt?->format('d.m.Y, H:i \U\h\r') ?? '';
        }

        return $variables;
    }
}
