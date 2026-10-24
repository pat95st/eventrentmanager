<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging;

use App\Domain\Messaging\MessagingChannel;
use App\Domain\Messaging\MessagingSettings;
use App\Domain\Messaging\MessagingSettingsRepositoryInterface;
use App\Infrastructure\Integration\HttpJsonRequest;

/**
 * Versendet Nachrichten über die drei unterstützten Messenger-APIs, über den
 * bereits vorhandenen curl-Helfer HttpJsonRequest (siehe dessen
 * Klassenkommentar — keine zusätzliche HTTP-Bibliothek). Liest die
 * Zugangsdaten bei JEDEM Aufruf frisch aus den Einstellungen (analog
 * SmtpMailer::send(), das genauso verfährt), statt sie sich beim Bootstrap
 * einmalig injizieren zu lassen — ein im Admin-Bereich geändertes Token
 * wirkt dadurch ohne Neustart.
 *
 *  - WhatsApp: Meta Graph API ("WhatsApp Business Cloud API"), Versand
 *    AUSSCHLIESSLICH über bei Meta genehmigte Message-Templates (siehe
 *    MessageTemplateKey-Klassenkommentar) — Referenz:
 *    https://developers.facebook.com/docs/whatsapp/cloud-api/reference/messages
 *  - Telegram: offizielle Bot-API (sendMessage) — Referenz:
 *    https://core.telegram.org/bots/api#sendmessage
 *  - Signal: signal-cli-rest-api (selbst gehostet, z. B. als Docker-
 *    Container bbernhard/signal-cli-rest-api), REST-Endpunkt "/v2/send" —
 *    Referenz: https://github.com/bbernhard/signal-cli-rest-api
 */
final class HttpMessageSender implements MessageSenderInterface
{
    private const GRAPH_VERSION = 'v21.0';
    private const GRAPH_BASE = 'https://graph.facebook.com/' . self::GRAPH_VERSION;
    private const TELEGRAM_BASE = 'https://api.telegram.org/bot';

    public function __construct(private readonly MessagingSettingsRepositoryInterface $settingsRepository)
    {
    }

    public function sendTelegramMessage(string $chatId, string $text): void
    {
        $settings = $this->requireConfigured(MessagingChannel::Telegram);

        $response = HttpJsonRequest::send(
            'POST',
            self::TELEGRAM_BASE . rawurlencode((string) $settings->telegramBotToken) . '/sendMessage',
            ['Content-Type' => 'application/json'],
            ['chat_id' => $chatId, 'text' => $text],
        );

        if ($response['status'] >= 400 || ($response['json']['ok'] ?? false) !== true) {
            throw new MessageSenderException($this->describeError('Telegram-Nachricht konnte nicht verschickt werden', $response));
        }
    }

    public function sendSignalMessage(string $phoneNumber, string $text): void
    {
        $settings = $this->requireConfigured(MessagingChannel::Signal);

        $response = HttpJsonRequest::send(
            'POST',
            rtrim((string) $settings->signalApiBaseUrl, '/') . '/v2/send',
            ['Content-Type' => 'application/json'],
            [
                'message' => $text,
                'number' => $settings->signalSenderNumber,
                'recipients' => [$phoneNumber],
            ],
        );

        if ($response['status'] >= 400) {
            throw new MessageSenderException($this->describeError('Signal-Nachricht konnte nicht verschickt werden', $response));
        }
    }

    public function sendWhatsappTemplateMessage(string $phoneNumber, string $templateName, string $languageCode, array $parameters): void
    {
        $settings = $this->requireConfigured(MessagingChannel::WhatsApp);

        $body = [
            'messaging_product' => 'whatsapp',
            'to' => $phoneNumber,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $languageCode],
            ],
        ];

        if ($parameters !== []) {
            $body['template']['components'] = [[
                'type' => 'body',
                'parameters' => array_map(static fn (string $value) => ['type' => 'text', 'text' => $value], $parameters),
            ]];
        }

        $response = HttpJsonRequest::send(
            'POST',
            self::GRAPH_BASE . '/' . rawurlencode((string) $settings->whatsappPhoneNumberId) . '/messages',
            ['Authorization' => 'Bearer ' . $settings->whatsappAccessToken, 'Content-Type' => 'application/json'],
            $body,
        );

        if ($response['status'] >= 400) {
            throw new MessageSenderException($this->describeError('WhatsApp-Nachricht konnte nicht verschickt werden', $response));
        }
    }

    public function testConnection(MessagingChannel $channel): string
    {
        $settings = $this->requireConfigured($channel);

        return match ($channel) {
            MessagingChannel::WhatsApp => $this->testWhatsapp($settings),
            MessagingChannel::Telegram => $this->testTelegram($settings),
            MessagingChannel::Signal => $this->testSignal($settings),
        };
    }

    private function testWhatsapp(MessagingSettings $settings): string
    {
        $response = HttpJsonRequest::send(
            'GET',
            self::GRAPH_BASE . '/' . rawurlencode((string) $settings->whatsappPhoneNumberId) . '?fields=display_phone_number,verified_name',
            ['Authorization' => 'Bearer ' . $settings->whatsappAccessToken],
        );

        if ($response['status'] >= 400 || $response['json'] === null) {
            throw new MessageSenderException($this->describeError('Verbindung zur WhatsApp Cloud API fehlgeschlagen', $response));
        }

        $name = $response['json']['verified_name'] ?? '?';
        $number = $response['json']['display_phone_number'] ?? '?';

        return "Verbunden als \"{$name}\" ({$number}).";
    }

    private function testTelegram(MessagingSettings $settings): string
    {
        $response = HttpJsonRequest::send('GET', self::TELEGRAM_BASE . rawurlencode((string) $settings->telegramBotToken) . '/getMe');

        if ($response['status'] >= 400 || ($response['json']['ok'] ?? false) !== true) {
            throw new MessageSenderException($this->describeError('Verbindung zur Telegram Bot-API fehlgeschlagen', $response));
        }

        $username = $response['json']['result']['username'] ?? '?';

        return "Verbunden als Bot \"@{$username}\".";
    }

    private function testSignal(MessagingSettings $settings): string
    {
        $response = HttpJsonRequest::send('GET', rtrim((string) $settings->signalApiBaseUrl, '/') . '/v1/about');

        if ($response['status'] >= 400) {
            throw new MessageSenderException($this->describeError('Verbindung zur signal-cli-rest-api fehlgeschlagen', $response));
        }

        $version = $response['json']['build'] ?? ($response['json']['version'] ?? '?');

        return "Verbunden (signal-cli-rest-api Version {$version}).";
    }

    private function requireConfigured(MessagingChannel $channel): MessagingSettings
    {
        $settings = $this->settingsRepository->get();

        $configured = match ($channel) {
            MessagingChannel::WhatsApp => $settings->isWhatsappConfigured(),
            MessagingChannel::Telegram => $settings->isTelegramConfigured(),
            MessagingChannel::Signal => $settings->isSignalConfigured(),
        };

        if (!$configured) {
            throw new MessageSenderException($channel->label() . ' ist noch nicht (vollständig) konfiguriert (siehe Einstellungen -> Nachrichtendienste).');
        }

        return $settings;
    }

    /** @param array{status: int, json: array<string, mixed>|null, raw: string} $response */
    private function describeError(string $prefix, array $response): string
    {
        $apiMessage = $response['json']['error']['message'] ?? ($response['json']['description'] ?? null);

        if (is_string($apiMessage) && $apiMessage !== '') {
            return $prefix . ' (HTTP ' . $response['status'] . '): ' . $apiMessage;
        }

        return $prefix . ' (HTTP ' . $response['status'] . ').';
    }
}
