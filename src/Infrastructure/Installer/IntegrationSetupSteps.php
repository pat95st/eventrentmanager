<?php

declare(strict_types=1);

namespace App\Infrastructure\Installer;

use App\Application\Datev\Command\UpdateDatevSettings;
use App\Application\Datev\Command\UpdateDatevSettingsHandler;
use App\Application\OnlinePayment\Command\UpdateOnlinePaymentSettings;
use App\Application\OnlinePayment\Command\UpdateOnlinePaymentSettingsHandler;
use App\Application\Routing\Command\SaveRoutingSettings;
use App\Application\Routing\Command\SaveRoutingSettingsHandler;
use App\Application\Settings\Command\UpdateAiAdImageSettings;
use App\Application\Settings\Command\UpdateAiAdImageSettingsHandler;
use App\Application\Settings\Command\UpdateGoogleBusinessClientCredentials;
use App\Application\Settings\Command\UpdateGoogleBusinessClientCredentialsHandler;
use App\Application\Settings\Command\UpdateGoogleMerchantSettings;
use App\Application\Settings\Command\UpdateGoogleMerchantSettingsHandler;
use App\Application\Settings\Command\UpdateIntegrationBaseSettings;
use App\Application\Settings\Command\UpdateIntegrationBaseSettingsHandler;
use App\Application\Settings\Command\UpdateWhatsappBusinessSettings;
use App\Application\Settings\Command\UpdateWhatsappBusinessSettingsHandler;
use App\Domain\Datev\DatevSettingsRepositoryInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\OnlinePayment\OnlinePaymentSettingsRepositoryInterface;
use App\Domain\Routing\RoutingSettingsRepositoryInterface;
use App\Domain\Settings\AiAdImageSettingsRepositoryInterface;
use App\Domain\Settings\GoogleBusinessSettingsRepositoryInterface;
use App\Domain\Settings\GoogleMerchantSettingsRepositoryInterface;
use App\Domain\Settings\IntegrationBaseSettingsRepositoryInterface;
use App\Domain\Settings\WhatsappBusinessSettingsRepositoryInterface;
use App\Infrastructure\Persistence\Pdo\MariadbAiAdImageSettingsRepository;
use App\Infrastructure\Persistence\Pdo\MariadbDatevSettingsRepository;
use App\Infrastructure\Persistence\Pdo\MariadbGoogleBusinessSettingsRepository;
use App\Infrastructure\Persistence\Pdo\MariadbGoogleMerchantSettingsRepository;
use App\Infrastructure\Persistence\Pdo\MariadbIntegrationBaseSettingsRepository;
use App\Infrastructure\Persistence\Pdo\MariadbOnlinePaymentSettingsRepository;
use App\Infrastructure\Persistence\Pdo\MariadbRoutingSettingsRepository;
use App\Infrastructure\Persistence\Pdo\MariadbWhatsappBusinessSettingsRepository;
use PDO;

/**
 * Optional setup steps of the web installer (public/install.php) for every
 * section of "Einstellungen → Integrationen" plus online payments, the
 * OpenAI key (AI images, value estimates, translation) and route planning.
 * Each step can be saved or
 * skipped; saving goes through the same command handlers as the settings
 * page, so validation and persistence behave identically.
 *
 * Only the fields that make sense before the first login are offered here.
 * Values not shown in the installer (e.g. DATEV account overrides restored
 * from a backup) are kept unchanged.
 */
final class IntegrationSetupSteps
{
    public const BASE_URL = 'base_url';
    public const ONLINE_PAYMENTS = 'online_payments';
    public const AI = 'ai';
    public const ROUTING = 'routing';
    public const GOOGLE_MERCHANT = 'google_merchant';
    public const GOOGLE_BUSINESS = 'google_business';
    public const WHATSAPP = 'whatsapp';
    public const DATEV = 'datev';

    /** Order in which the installer presents the steps. */
    public const STEPS = [
        self::BASE_URL,
        self::ONLINE_PAYMENTS,
        self::AI,
        self::ROUTING,
        self::GOOGLE_MERCHANT,
        self::GOOGLE_BUSINESS,
        self::WHATSAPP,
        self::DATEV,
    ];

    private const TITLES = [
        self::BASE_URL => 'Basis-URL',
        self::ONLINE_PAYMENTS => 'Online-Zahlungen (PayPal, SumUp)',
        self::AI => 'KI-Funktionen (OpenAI)',
        self::ROUTING => 'Routenplanung (OpenRouteService)',
        self::GOOGLE_MERCHANT => 'Google Merchant Center',
        self::GOOGLE_BUSINESS => 'Google Unternehmensprofil',
        self::WHATSAPP => 'WhatsApp Business',
        self::DATEV => 'DATEV-Export',
    ];

    public function __construct(
        private readonly IntegrationBaseSettingsRepositoryInterface $baseSettings,
        private readonly GoogleMerchantSettingsRepositoryInterface $googleMerchantSettings,
        private readonly GoogleBusinessSettingsRepositoryInterface $googleBusinessSettings,
        private readonly WhatsappBusinessSettingsRepositoryInterface $whatsappSettings,
        private readonly DatevSettingsRepositoryInterface $datevSettings,
        private readonly OnlinePaymentSettingsRepositoryInterface $onlinePaymentSettings,
        private readonly AiAdImageSettingsRepositoryInterface $aiSettings,
        private readonly RoutingSettingsRepositoryInterface $routingSettings,
    ) {
    }

    public static function forPdo(PDO $pdo): self
    {
        return new self(
            new MariadbIntegrationBaseSettingsRepository($pdo),
            new MariadbGoogleMerchantSettingsRepository($pdo),
            new MariadbGoogleBusinessSettingsRepository($pdo),
            new MariadbWhatsappBusinessSettingsRepository($pdo),
            new MariadbDatevSettingsRepository($pdo),
            new MariadbOnlinePaymentSettingsRepository($pdo),
            new MariadbAiAdImageSettingsRepository($pdo),
            new MariadbRoutingSettingsRepository($pdo),
        );
    }

    public static function isStep(string $step): bool
    {
        return in_array($step, self::STEPS, true);
    }

    public static function title(string $step): string
    {
        return self::TITLES[$step] ?? $step;
    }

    /** Next step after $step, or null if $step is the last one. */
    public static function next(string $step): ?string
    {
        $index = array_search($step, self::STEPS, true);
        if ($index === false) {
            return null;
        }

        return self::STEPS[$index + 1] ?? null;
    }

    /**
     * Current values used to prefill the installer form of $step (e.g. after
     * restoring a backup, or after a failed validation they are overridden
     * by the submitted input).
     *
     * @return array<string, string|bool>
     */
    public function currentValues(string $step): array
    {
        switch ($step) {
            case self::BASE_URL:
                return ['base_url' => $this->baseSettings->get()->baseUrl ?? ''];

            case self::ONLINE_PAYMENTS:
                $payments = $this->onlinePaymentSettings->get();

                return [
                    'paypal_enabled' => $payments->paypalEnabled,
                    'paypal_sandbox' => $payments->paypalSandbox,
                    'paypal_client_id' => $payments->paypalClientId ?? '',
                    // Stored secrets are never echoed back into the form.
                    'paypal_client_secret' => '',
                    'has_paypal_client_secret' => $payments->paypalClientSecret !== null,
                    'sumup_enabled' => $payments->sumupEnabled,
                    'sumup_api_key' => '',
                    'has_sumup_api_key' => $payments->sumupApiKey !== null,
                    'sumup_merchant_code' => $payments->sumupMerchantCode ?? '',
                    'currency' => $payments->currency,
                ];

            case self::AI:
                $ai = $this->aiSettings->get();

                return [
                    'openai_api_key' => '',
                    'has_openai_api_key' => $ai->isConfigured(),
                    'image_model' => $ai->imageModel ?? '',
                ];

            case self::ROUTING:
                $routing = $this->routingSettings->get();

                return [
                    'api_key' => '',
                    'has_api_key' => $routing->hasApiKey(),
                    'buffer_minutes' => (string) $routing->bufferMinutes,
                ];

            case self::GOOGLE_MERCHANT:
                $merchant = $this->googleMerchantSettings->get();

                return [
                    'merchant_id' => $merchant->merchantId ?? '',
                    'target_country' => $merchant->targetCountry,
                    'content_language' => $merchant->contentLanguage,
                    'product_link_template' => $merchant->productLinkTemplate ?? '',
                ];

            case self::GOOGLE_BUSINESS:
                $business = $this->googleBusinessSettings->get();

                return [
                    'client_id' => $business->clientId ?? '',
                    // The stored secret is never echoed back into the form.
                    'client_secret' => '',
                    'has_client_secret' => $business->clientSecret !== null && $business->clientSecret !== '',
                ];

            case self::WHATSAPP:
                $whatsapp = $this->whatsappSettings->get();

                return [
                    // The stored token is never echoed back into the form.
                    'access_token' => '',
                    'has_access_token' => $whatsapp->accessToken !== null && $whatsapp->accessToken !== '',
                    'catalog_id' => $whatsapp->catalogId ?? '',
                    'currency' => $whatsapp->currency,
                ];

            case self::DATEV:
                $datev = $this->datevSettings->get();

                return [
                    'datev_enabled' => $datev->enabled,
                    'datev_consultant_number' => $datev->consultantNumber !== null ? (string) $datev->consultantNumber : '',
                    'datev_client_number' => $datev->clientNumber !== null ? (string) $datev->clientNumber : '',
                    'datev_chart_of_accounts' => $datev->chartOfAccounts->value,
                    'datev_account_length' => (string) $datev->accountLength,
                    'datev_fiscal_year_start_month' => (string) $datev->fiscalYearStartMonth,
                    'datev_advisor_email' => $datev->advisorEmail ?? '',
                ];
        }

        throw new \InvalidArgumentException('Unknown integration step: ' . $step);
    }

    /**
     * Saves the submitted form of $step.
     *
     * @param array<string, mixed> $input raw POST data
     * @throws ValidationException
     */
    public function save(string $step, array $input): void
    {
        $value = static fn (string $key): ?string => isset($input[$key]) && is_scalar($input[$key]) ? (string) $input[$key] : null;

        switch ($step) {
            case self::BASE_URL:
                (new UpdateIntegrationBaseSettingsHandler($this->baseSettings))
                    ->handle(new UpdateIntegrationBaseSettings($value('base_url')));

                return;

            case self::ONLINE_PAYMENTS:
                // Empty secret fields keep the stored values (handled by the command handler).
                (new UpdateOnlinePaymentSettingsHandler($this->onlinePaymentSettings))->handle(new UpdateOnlinePaymentSettings(
                    paypalEnabled: $value('paypal_enabled') === '1',
                    paypalSandbox: $value('paypal_sandbox') === '1',
                    paypalClientId: $value('paypal_client_id'),
                    paypalClientSecret: $value('paypal_client_secret'),
                    sumupEnabled: $value('sumup_enabled') === '1',
                    sumupApiKey: $value('sumup_api_key'),
                    sumupMerchantCode: $value('sumup_merchant_code'),
                    currency: $value('currency') ?? 'EUR',
                ));

                return;

            case self::AI:
                // An empty key keeps the stored one (handled by the command handler).
                (new UpdateAiAdImageSettingsHandler($this->aiSettings))->handle(
                    new UpdateAiAdImageSettings($value('openai_api_key'), $value('image_model'))
                );

                return;

            case self::ROUTING:
                // An empty key keeps the stored one (handled by the command handler).
                (new SaveRoutingSettingsHandler($this->routingSettings))->handle(
                    new SaveRoutingSettings($value('api_key') ?? '', $value('buffer_minutes') ?? '30')
                );

                return;

            case self::GOOGLE_MERCHANT:
                (new UpdateGoogleMerchantSettingsHandler($this->googleMerchantSettings))->handle(new UpdateGoogleMerchantSettings(
                    merchantId: $value('merchant_id'),
                    targetCountry: $value('target_country') ?? 'DE',
                    contentLanguage: $value('content_language') ?? 'de',
                    productLinkTemplate: $value('product_link_template'),
                ));

                return;

            case self::GOOGLE_BUSINESS:
                $clientSecret = $value('client_secret');
                if (trim((string) $clientSecret) === '') {
                    // Keep an already stored secret (e.g. from a restored backup).
                    $clientSecret = $this->googleBusinessSettings->get()->clientSecret;
                }
                (new UpdateGoogleBusinessClientCredentialsHandler($this->googleBusinessSettings))->handle(
                    new UpdateGoogleBusinessClientCredentials(clientId: $value('client_id'), clientSecret: $clientSecret)
                );

                return;

            case self::WHATSAPP:
                $accessToken = $value('access_token');
                if (trim((string) $accessToken) === '') {
                    // Keep an already stored token (e.g. from a restored backup).
                    $accessToken = $this->whatsappSettings->get()->accessToken;
                }
                (new UpdateWhatsappBusinessSettingsHandler($this->whatsappSettings))->handle(new UpdateWhatsappBusinessSettings(
                    accessToken: $accessToken,
                    catalogId: $value('catalog_id'),
                    currency: $value('currency') ?? 'EUR',
                ));

                return;

            case self::DATEV:
                $this->saveDatev($input, $value);

                return;
        }

        throw new \InvalidArgumentException('Unknown integration step: ' . $step);
    }

    /**
     * @param array<string, mixed> $input
     * @param callable(string): ?string $value
     */
    private function saveDatev(array $input, callable $value): void
    {
        // Fields the installer does not show are taken over from the current
        // settings, so nothing restored from a backup gets lost.
        $current = $this->datevSettings->get();
        $int = static fn (?int $number): ?string => $number !== null ? (string) $number : null;

        $accounts = [];
        foreach ($current->accounts as $role => $account) {
            $accounts[$role] = $int($account);
        }

        (new UpdateDatevSettingsHandler($this->datevSettings))->handle(new UpdateDatevSettings(
            enabled: isset($input['datev_enabled']) && $input['datev_enabled'] !== '',
            consultantNumber: $value('datev_consultant_number'),
            clientNumber: $value('datev_client_number'),
            chartOfAccounts: $value('datev_chart_of_accounts'),
            accountLength: $value('datev_account_length'),
            fiscalYearStartMonth: $value('datev_fiscal_year_start_month'),
            debtorMode: $current->debtorMode->value,
            debtorAccount: $int($current->debtorAccount),
            creditorAccount: $int($current->creditorAccount),
            accounts: $accounts,
            receiptTaxKey: $current->receiptTaxKey,
            includeReceipts: $current->includeReceipts,
            includeDocuments: $current->includeDocuments,
            advisorEmail: $value('datev_advisor_email'),
            autoSendEnabled: $current->autoSendEnabled,
            interval: $current->interval->value,
            sendDay: (string) $current->sendDay,
        ));
    }
}
