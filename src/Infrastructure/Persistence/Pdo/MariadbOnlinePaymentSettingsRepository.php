<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\OnlinePayment\OnlinePaymentSettings;
use App\Domain\OnlinePayment\OnlinePaymentSettingsRepositoryInterface;
use PDO;
use RuntimeException;

final class MariadbOnlinePaymentSettingsRepository implements OnlinePaymentSettingsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(): OnlinePaymentSettings
    {
        $row = $this->pdo->query('SELECT * FROM online_payment_settings WHERE id = 1')->fetch();
        if ($row === false) {
            throw new RuntimeException('Online-Zahlungs-Einstellungen wurden nicht gefunden — fehlt Migration 163?');
        }

        return new OnlinePaymentSettings(
            paypalEnabled: (bool) $row['paypal_enabled'],
            paypalSandbox: (bool) $row['paypal_sandbox'],
            paypalClientId: $row['paypal_client_id'],
            paypalClientSecret: $row['paypal_client_secret'],
            sumupEnabled: (bool) $row['sumup_enabled'],
            sumupApiKey: $row['sumup_api_key'],
            sumupMerchantCode: $row['sumup_merchant_code'],
            currency: $row['currency'],
        );
    }

    public function save(OnlinePaymentSettings $settings): void
    {
        $this->pdo->prepare(
            'UPDATE online_payment_settings SET
                paypal_enabled = :paypal_enabled,
                paypal_sandbox = :paypal_sandbox,
                paypal_client_id = :paypal_client_id,
                paypal_client_secret = :paypal_client_secret,
                sumup_enabled = :sumup_enabled,
                sumup_api_key = :sumup_api_key,
                sumup_merchant_code = :sumup_merchant_code,
                currency = :currency
             WHERE id = 1'
        )->execute([
            'paypal_enabled' => (int) $settings->paypalEnabled,
            'paypal_sandbox' => (int) $settings->paypalSandbox,
            'paypal_client_id' => $settings->paypalClientId,
            'paypal_client_secret' => $settings->paypalClientSecret,
            'sumup_enabled' => (int) $settings->sumupEnabled,
            'sumup_api_key' => $settings->sumupApiKey,
            'sumup_merchant_code' => $settings->sumupMerchantCode,
            'currency' => $settings->currency,
        ]);
    }
}
