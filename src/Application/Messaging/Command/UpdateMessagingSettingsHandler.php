<?php

declare(strict_types=1);

namespace App\Application\Messaging\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Messaging\MessagingSettings;
use App\Domain\Messaging\MessagingSettingsRepositoryInterface;

final class UpdateMessagingSettingsHandler implements CommandHandlerInterface
{
    public function __construct(private readonly MessagingSettingsRepositoryInterface $settingsRepository)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateMessagingSettings);

        $errors = [];

        $whatsappPhoneNumberId = $this->nullIfEmpty($command->whatsappPhoneNumberId);
        $whatsappBusinessAccountId = $this->nullIfEmpty($command->whatsappBusinessAccountId);
        if ($command->whatsappEnabled && $whatsappPhoneNumberId === null) {
            $errors['whatsappPhoneNumberId'] = 'Bitte die Phone-Number-ID angeben, um WhatsApp zu aktivieren.';
        }

        $telegramBotToken = $this->nullIfEmpty($command->telegramBotToken);

        $signalApiBaseUrl = $this->nullIfEmpty($command->signalApiBaseUrl);
        if ($signalApiBaseUrl !== null && filter_var($signalApiBaseUrl, FILTER_VALIDATE_URL) === false) {
            $errors['signalApiBaseUrl'] = 'Bitte eine gültige URL angeben (z. B. http://localhost:8080).';
        }
        $signalSenderNumber = $this->nullIfEmpty($command->signalSenderNumber);
        if ($command->signalEnabled && ($signalApiBaseUrl === null || $signalSenderNumber === null)) {
            $errors['signalEnabled'] = 'Bitte Server-URL und Absendernummer angeben, um Signal zu aktivieren.';
        }
        if ($command->telegramEnabled && $telegramBotToken === null) {
            $errors['telegramEnabled'] = 'Bitte ein Bot-Token angeben, um Telegram zu aktivieren.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $current = $this->settingsRepository->get();

        $resolvedWhatsappAccessToken = $this->nullIfEmpty($command->whatsappAccessToken) ?? $current->whatsappAccessToken;
        if ($command->whatsappEnabled && $resolvedWhatsappAccessToken === null) {
            $errors['whatsappAccessToken'] = 'Bitte ein Access-Token angeben, um WhatsApp zu aktivieren.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $settings = new MessagingSettings(
            whatsappEnabled: $command->whatsappEnabled,
            whatsappPhoneNumberId: $whatsappPhoneNumberId,
            // Leeres Feld im Formular = Zugangsdaten unverändert lassen,
            // analog UpdateSmtpSettingsHandler::$password.
            whatsappAccessToken: $resolvedWhatsappAccessToken,
            whatsappBusinessAccountId: $whatsappBusinessAccountId,
            telegramEnabled: $command->telegramEnabled,
            telegramBotToken: $telegramBotToken ?? $current->telegramBotToken,
            signalEnabled: $command->signalEnabled,
            signalApiBaseUrl: $signalApiBaseUrl,
            signalSenderNumber: $signalSenderNumber,
        );

        $this->settingsRepository->save($settings);

        return null;
    }

    private function nullIfEmpty(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
    }
}
