<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Settings\GoogleMerchantSettings;
use App\Domain\Settings\GoogleMerchantSettingsRepositoryInterface;
use PDO;
use RuntimeException;

final class MariadbGoogleMerchantSettingsRepository implements GoogleMerchantSettingsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(): GoogleMerchantSettings
    {
        $stmt = $this->pdo->query('SELECT * FROM google_merchant_settings WHERE id = 1');
        $row = $stmt->fetch();

        if ($row === false) {
            throw new RuntimeException('Google-Merchant-Einstellungen wurden nicht gefunden — fehlt die entsprechende Migration?');
        }

        return new GoogleMerchantSettings(
            merchantId: $row['merchant_id'],
            serviceAccountJsonFilename: $row['service_account_json_filename'],
            targetCountry: $row['target_country'],
            contentLanguage: $row['content_language'],
            productLinkTemplate: $row['product_link_template'],
        );
    }

    public function save(GoogleMerchantSettings $settings): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE google_merchant_settings SET
                merchant_id = :merchant_id,
                service_account_json_filename = :service_account_json_filename,
                target_country = :target_country,
                content_language = :content_language,
                product_link_template = :product_link_template
             WHERE id = 1'
        );

        $stmt->execute([
            'merchant_id' => $settings->merchantId,
            'service_account_json_filename' => $settings->serviceAccountJsonFilename,
            'target_country' => $settings->targetCountry,
            'content_language' => $settings->contentLanguage,
            'product_link_template' => $settings->productLinkTemplate,
        ]);
    }
}
