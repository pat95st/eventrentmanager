<?php

declare(strict_types=1);

namespace App\Application\Messaging\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\Messaging\MessagingTemplateRenderer;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Messaging\MessageTemplateKey;
use App\Domain\Messaging\MessagingChannel;
use App\Domain\Messaging\MessagingSettingsRepositoryInterface;
use App\Infrastructure\Messaging\MessageSenderException;
use App\Infrastructure\Messaging\MessageSenderInterface;

/**
 * Verschickt eine freie Einzel-Nachricht an einen Kunden über die
 * Kundendetailseite ("Nachricht senden"). Bei Telegram/Signal wird der
 * eingegebene Text direkt verschickt; bei WhatsApp wird er als EINZIGER
 * Platzhalter in die hinterlegte, bei Meta genehmigte AdHocMessage-Vorlage
 * eingesetzt (siehe MessageTemplateKey-Klassenkommentar — freier Text ist
 * dort technisch nicht möglich).
 */
final class SendAdHocMessageToCustomerHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customers,
        private readonly MessagingSettingsRepositoryInterface $messagingSettingsRepository,
        private readonly MessagingTemplateRenderer $templateRenderer,
        private readonly MessageSenderInterface $sender,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof SendAdHocMessageToCustomer);

        $customer = $this->customers->findById($command->customerId);
        if ($customer === null) {
            throw new NotFoundException("Kunde \"{$command->customerId}\" wurde nicht gefunden.");
        }

        $text = trim($command->text);
        if ($text === '') {
            throw new ValidationException(['text' => 'Bitte einen Nachrichtentext angeben.']);
        }

        $settings = $this->messagingSettingsRepository->get();
        if (!$settings->isChannelReady($command->channel)) {
            throw new ValidationException(['channel' => $command->channel->label() . ' ist nicht aktiviert oder nicht vollständig konfiguriert (siehe Einstellungen -> Nachrichtendienste).']);
        }

        try {
            match ($command->channel) {
                MessagingChannel::Telegram => $this->sendTelegram($customer->telegramChatId, $text),
                MessagingChannel::Signal => $this->sendSignal($customer->phone, $text),
                MessagingChannel::WhatsApp => $this->sendWhatsapp($customer->phone, $text),
            };
        } catch (MessageSenderException $e) {
            throw new ValidationException(['send' => $e->getMessage()]);
        }

        return null;
    }

    private function sendTelegram(?string $telegramChatId, string $text): void
    {
        if ($telegramChatId === null || trim($telegramChatId) === '') {
            throw new ValidationException(['channel' => 'Für diesen Kunden ist keine Telegram-Chat-ID hinterlegt.']);
        }

        $this->sender->sendTelegramMessage($telegramChatId, $text);
    }

    private function sendSignal(?string $phone, string $text): void
    {
        if ($phone === null || trim($phone) === '') {
            throw new ValidationException(['channel' => 'Für diesen Kunden ist keine Telefonnummer hinterlegt.']);
        }

        $this->sender->sendSignalMessage($phone, $text);
    }

    private function sendWhatsapp(?string $phone, string $text): void
    {
        if ($phone === null || trim($phone) === '') {
            throw new ValidationException(['channel' => 'Für diesen Kunden ist keine Telefonnummer hinterlegt.']);
        }

        $resolved = $this->templateRenderer->resolveWhatsappParameters(MessageTemplateKey::AdHocMessage, ['message_text' => $text]);

        if ($resolved['templateName'] === '') {
            throw new ValidationException(['channel' => 'Für WhatsApp ist noch keine Vorlage für freie Nachrichten hinterlegt (siehe Einstellungen -> Nachrichtendienste-Vorlagen).']);
        }

        $this->sender->sendWhatsappTemplateMessage($phone, $resolved['templateName'], $resolved['languageCode'], $resolved['parameters']);
    }
}
