<?php

declare(strict_types=1);

namespace App\Application\Dunning\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\Dunning\Query\DunningNoticeDetail;
use App\Application\Dunning\Query\GetDunningNotice;
use App\Application\Dunning\Query\GetDunningNoticeHandler;
use App\Application\Messaging\MessagingSendResult;
use App\Application\Messaging\MessagingTemplateRenderer;
use App\Domain\Customer\Customer;
use App\Domain\Messaging\MessageTemplateKey;
use App\Domain\Messaging\MessagingChannel;
use App\Domain\Messaging\MessagingSettingsRepositoryInterface;
use App\Infrastructure\Messaging\MessageSenderException;
use App\Infrastructure\Messaging\MessageSenderInterface;

/**
 * Verschickt eine Mahnungs-Benachrichtigung ZUSÄTZLICH über jeden
 * aktivierten UND konfigurierten Messenger, für den der Kunde die nötigen
 * Kontaktdaten hinterlegt hat — analog SendRentalReminderViaMessagingHandler,
 * hier neben EmailDunningNotice dispatcht (siehe bin/send-dunning-notices.php
 * und ggf. DunningController für den manuellen Versand).
 *
 * Fehlt dem Kunden sowohl Telefonnummer (WhatsApp/Signal) als auch
 * Telegram-Chat-ID, passiert einfach nichts (kein Fehler) — der E-Mail-
 * Versand bleibt der primäre, immer verlässliche Kanal.
 */
final class SendDunningNoticeViaMessagingHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly GetDunningNoticeHandler $getDunningNoticeHandler,
        private readonly MessagingSettingsRepositoryInterface $messagingSettingsRepository,
        private readonly MessagingTemplateRenderer $templateRenderer,
        private readonly MessageSenderInterface $sender,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof SendDunningNoticeViaMessaging);

        // Wirft NotFoundException, falls die Mahnung nicht existiert.
        $detail = $this->getDunningNoticeHandler->handle(new GetDunningNotice($command->dunningNoticeId));
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
                $resolved = $this->templateRenderer->resolveWhatsappParameters(MessageTemplateKey::DunningNotice, $variables);
                if ($resolved['templateName'] === '') {
                    throw new MessageSenderException('Für WhatsApp ist noch keine Vorlage für "' . MessageTemplateKey::DunningNotice->label() . '" hinterlegt.');
                }
                $this->sender->sendWhatsappTemplateMessage((string) $customer->phone, $resolved['templateName'], $resolved['languageCode'], $resolved['parameters']);
                $sent[] = MessagingChannel::WhatsApp;
            } catch (MessageSenderException $e) {
                $failed[MessagingChannel::WhatsApp->value] = $e->getMessage();
            }
        }

        if ($settings->isChannelReady(MessagingChannel::Signal) && $this->hasPhone($customer)) {
            try {
                $text = $this->templateRenderer->renderPlainText(MessagingChannel::Signal, MessageTemplateKey::DunningNotice, $variables);
                $this->sender->sendSignalMessage((string) $customer->phone, $text);
                $sent[] = MessagingChannel::Signal;
            } catch (MessageSenderException $e) {
                $failed[MessagingChannel::Signal->value] = $e->getMessage();
            }
        }

        if ($settings->isChannelReady(MessagingChannel::Telegram) && $this->hasTelegramChatId($customer)) {
            try {
                $text = $this->templateRenderer->renderPlainText(MessagingChannel::Telegram, MessageTemplateKey::DunningNotice, $variables);
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
    private function buildVariables(DunningNoticeDetail $detail): array
    {
        return [
            'customer_name' => $detail->customer?->displayName() ?? '',
            'invoice_number' => $detail->invoice->invoiceNumber ?? '',
            'dunning_level_name' => $detail->notice->levelName,
            'total_due' => number_format($detail->notice->totalDue(), 2, ',', '.') . ' €',
        ];
    }
}
