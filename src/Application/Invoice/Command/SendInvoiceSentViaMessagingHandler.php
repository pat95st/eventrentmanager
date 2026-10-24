<?php

declare(strict_types=1);

namespace App\Application\Invoice\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\Invoice\Query\GetInvoice;
use App\Application\Invoice\Query\GetInvoiceHandler;
use App\Application\Invoice\Query\InvoiceDetail;
use App\Application\Messaging\MessagingSendResult;
use App\Application\Messaging\MessagingTemplateRenderer;
use App\Domain\Customer\Customer;
use App\Domain\Messaging\MessageTemplateKey;
use App\Domain\Messaging\MessagingChannel;
use App\Domain\Messaging\MessagingSettingsRepositoryInterface;
use App\Domain\Settings\InvoiceSettingsRepositoryInterface;
use App\Infrastructure\Messaging\MessageSenderException;
use App\Infrastructure\Messaging\MessageSenderInterface;

/**
 * Verschickt eine Benachrichtigung über den Rechnungsversand ZUSÄTZLICH über
 * jeden aktivierten UND konfigurierten Messenger, für den der Kunde die
 * nötigen Kontaktdaten hinterlegt hat — analog
 * SendRentalReminderViaMessagingHandler, hier aber neben EmailInvoicePdf
 * dispatcht (siehe InvoiceController::emailPdf()), NICHT neben MarkInvoiceSent
 * (reine Statusänderung, siehe MarkInvoiceSentHandler-Klassendoc).
 *
 * Fehlt dem Kunden sowohl Telefonnummer (WhatsApp/Signal) als auch
 * Telegram-Chat-ID, passiert einfach nichts (kein Fehler) — der Aufrufer
 * (InvoiceController) muss das Ergebnis nicht auswerten, der E-Mail-Versand
 * bleibt der primäre, immer verlässliche Kanal.
 */
final class SendInvoiceSentViaMessagingHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly GetInvoiceHandler $getInvoiceHandler,
        private readonly InvoiceSettingsRepositoryInterface $invoiceSettingsRepository,
        private readonly MessagingSettingsRepositoryInterface $messagingSettingsRepository,
        private readonly MessagingTemplateRenderer $templateRenderer,
        private readonly MessageSenderInterface $sender,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof SendInvoiceSentViaMessaging);

        // Wirft NotFoundException, falls die Rechnung nicht existiert.
        $detail = $this->getInvoiceHandler->handle(new GetInvoice($command->invoiceId));
        $customer = $detail->customer;

        if ($customer === null) {
            return new MessagingSendResult([], []);
        }

        $settings = $this->messagingSettingsRepository->get();
        $variables = $this->buildVariables($detail);

        $sent = [];
        $failed = [];

        if ($settings->isChannelReady(MessagingChannel::WhatsApp) && $this->hasPhone($customer)) {
            try {
                $resolved = $this->templateRenderer->resolveWhatsappParameters(MessageTemplateKey::InvoiceSent, $variables);
                if ($resolved['templateName'] === '') {
                    throw new MessageSenderException('Für WhatsApp ist noch keine Vorlage für "' . MessageTemplateKey::InvoiceSent->label() . '" hinterlegt.');
                }
                $this->sender->sendWhatsappTemplateMessage((string) $customer->phone, $resolved['templateName'], $resolved['languageCode'], $resolved['parameters']);
                $sent[] = MessagingChannel::WhatsApp;
            } catch (MessageSenderException $e) {
                $failed[MessagingChannel::WhatsApp->value] = $e->getMessage();
            }
        }

        if ($settings->isChannelReady(MessagingChannel::Signal) && $this->hasPhone($customer)) {
            try {
                $text = $this->templateRenderer->renderPlainText(MessagingChannel::Signal, MessageTemplateKey::InvoiceSent, $variables);
                $this->sender->sendSignalMessage((string) $customer->phone, $text);
                $sent[] = MessagingChannel::Signal;
            } catch (MessageSenderException $e) {
                $failed[MessagingChannel::Signal->value] = $e->getMessage();
            }
        }

        if ($settings->isChannelReady(MessagingChannel::Telegram) && $this->hasTelegramChatId($customer)) {
            try {
                $text = $this->templateRenderer->renderPlainText(MessagingChannel::Telegram, MessageTemplateKey::InvoiceSent, $variables);
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
    private function buildVariables(InvoiceDetail $detail): array
    {
        $invoice = $detail->invoice;
        $invoiceSettings = $this->invoiceSettingsRepository->get();
        $totals = $detail->totals($invoiceSettings);

        return [
            'customer_name' => $detail->customer?->displayName() ?? '',
            'invoice_number' => $invoice->invoiceNumber ?? '',
            'due_date' => $invoice->dueDate?->format('d.m.Y') ?? '',
            'total_gross' => number_format($totals->grossTotal, 2, ',', '.') . ' €',
        ];
    }
}
