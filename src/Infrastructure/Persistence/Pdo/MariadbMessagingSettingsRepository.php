<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Messaging\MessagingSettings;
use App\Domain\Messaging\MessagingSettingsRepositoryInterface;
use PDO;
use RuntimeException;

final class MariadbMessagingSettingsRepository implements MessagingSettingsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(): MessagingSettings
    {
        $stmt = $this->pdo->query('SELECT * FROM messaging_settings WHERE id = 1');
        $row = $stmt->fetch();

        if ($row === false) {
            throw new RuntimeException('Messenger-Einstellungen wurden nicht gefunden — fehlt die Migration 077?');
        }

        return new MessagingSettings(
            whatsappEnabled: (bool) $row['whatsapp_enabled'],
            whatsappPhoneNumberId: $row['whatsapp_phone_number_id'],
            whatsappAccessToken: $row['whatsapp_access_token'],
            whatsappBusinessAccountId: $row['whatsapp_business_account_id'],
            telegramEnabled: (bool) $row['telegram_enabled'],
            telegramBotToken: $row['telegram_bot_token'],
            signalEnabled: (bool) $row['signal_enabled'],
            signalApiBaseUrl: $row['signal_api_base_url'],
            signalSenderNumber: $row['signal_sender_number'],
        );
    }

    public function save(MessagingSettings $settings): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE messaging_settings SET
                whatsapp_enabled = :whatsapp_enabled,
                whatsapp_phone_number_id = :whatsapp_phone_number_id,
                whatsapp_access_token = :whatsapp_access_token,
                whatsapp_business_account_id = :whatsapp_business_account_id,
                telegram_enabled = :telegram_enabled,
                telegram_bot_token = :telegram_bot_token,
                signal_enabled = :signal_enabled,
                signal_api_base_url = :signal_api_base_url,
                signal_sender_number = :signal_sender_number
             WHERE id = 1'
        );

        $stmt->execute([
            'whatsapp_enabled' => $settings->whatsappEnabled ? 1 : 0,
            'whatsapp_phone_number_id' => $settings->whatsappPhoneNumberId,
            'whatsapp_access_token' => $settings->whatsappAccessToken,
            'whatsapp_business_account_id' => $settings->whatsappBusinessAccountId,
            'telegram_enabled' => $settings->telegramEnabled ? 1 : 0,
            'telegram_bot_token' => $settings->telegramBotToken,
            'signal_enabled' => $settings->signalEnabled ? 1 : 0,
            'signal_api_base_url' => $settings->signalApiBaseUrl,
            'signal_sender_number' => $settings->signalSenderNumber,
        ]);
    }
}
