<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Installer;

use App\Domain\Datev\DatevChartOfAccounts;
use App\Domain\Datev\DatevSettings;
use App\Domain\Datev\DatevSettingsRepositoryInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\OnlinePayment\OnlinePaymentSettings;
use App\Domain\Routing\RoutingSettings;
use App\Domain\Routing\RoutingSettingsRepositoryInterface;
use App\Domain\Settings\AiAdImageSettings;
use App\Domain\Settings\AiAdImageSettingsRepositoryInterface;
use App\Domain\Settings\GoogleBusinessSettings;
use App\Domain\Settings\GoogleBusinessSettingsRepositoryInterface;
use App\Domain\Settings\GoogleMerchantSettings;
use App\Domain\Settings\GoogleMerchantSettingsRepositoryInterface;
use App\Domain\Settings\IntegrationBaseSettings;
use App\Domain\Settings\IntegrationBaseSettingsRepositoryInterface;
use App\Domain\Settings\WhatsappBusinessSettings;
use App\Domain\Settings\WhatsappBusinessSettingsRepositoryInterface;
use App\Infrastructure\Installer\IntegrationSetupSteps;
use Codeception\Test\Unit;
use Tests\Support\Fakes\InMemoryOnlinePaymentSettingsRepository;

final class IntegrationSetupStepsTest extends Unit
{
    private IntegrationBaseSettingsRepositoryInterface $base;
    private GoogleMerchantSettingsRepositoryInterface $merchant;
    private GoogleBusinessSettingsRepositoryInterface $business;
    private WhatsappBusinessSettingsRepositoryInterface $whatsapp;
    private DatevSettingsRepositoryInterface $datev;
    private InMemoryOnlinePaymentSettingsRepository $payments;
    private AiAdImageSettingsRepositoryInterface $ai;
    private RoutingSettingsRepositoryInterface $routing;
    private IntegrationSetupSteps $steps;

    protected function _before(): void
    {
        $this->base = new class () implements IntegrationBaseSettingsRepositoryInterface {
            public IntegrationBaseSettings $settings;

            public function __construct()
            {
                $this->settings = new IntegrationBaseSettings();
            }

            public function get(): IntegrationBaseSettings
            {
                return clone $this->settings;
            }

            public function save(IntegrationBaseSettings $settings): void
            {
                $this->settings = clone $settings;
            }
        };
        $this->merchant = new class () implements GoogleMerchantSettingsRepositoryInterface {
            public GoogleMerchantSettings $settings;

            public function __construct()
            {
                $this->settings = new GoogleMerchantSettings();
            }

            public function get(): GoogleMerchantSettings
            {
                return clone $this->settings;
            }

            public function save(GoogleMerchantSettings $settings): void
            {
                $this->settings = clone $settings;
            }
        };
        $this->business = new class () implements GoogleBusinessSettingsRepositoryInterface {
            public GoogleBusinessSettings $settings;

            public function __construct()
            {
                $this->settings = new GoogleBusinessSettings();
            }

            public function get(): GoogleBusinessSettings
            {
                return clone $this->settings;
            }

            public function save(GoogleBusinessSettings $settings): void
            {
                $this->settings = clone $settings;
            }
        };
        $this->whatsapp = new class () implements WhatsappBusinessSettingsRepositoryInterface {
            public WhatsappBusinessSettings $settings;

            public function __construct()
            {
                $this->settings = new WhatsappBusinessSettings();
            }

            public function get(): WhatsappBusinessSettings
            {
                return clone $this->settings;
            }

            public function save(WhatsappBusinessSettings $settings): void
            {
                $this->settings = clone $settings;
            }
        };
        $this->datev = new class () implements DatevSettingsRepositoryInterface {
            public DatevSettings $settings;

            public function __construct()
            {
                $this->settings = new DatevSettings();
            }

            public function get(): DatevSettings
            {
                return clone $this->settings;
            }

            public function save(DatevSettings $settings): void
            {
                $this->settings = clone $settings;
            }
        };

        $this->payments = new InMemoryOnlinePaymentSettingsRepository();
        $this->ai = new class () implements AiAdImageSettingsRepositoryInterface {
            public AiAdImageSettings $settings;

            public function __construct()
            {
                $this->settings = new AiAdImageSettings();
            }

            public function get(): AiAdImageSettings
            {
                return clone $this->settings;
            }

            public function save(AiAdImageSettings $settings): void
            {
                $this->settings = clone $settings;
            }
        };
        $this->routing = new class () implements RoutingSettingsRepositoryInterface {
            public RoutingSettings $settings;

            public function __construct()
            {
                $this->settings = new RoutingSettings();
            }

            public function get(): RoutingSettings
            {
                return clone $this->settings;
            }

            public function save(RoutingSettings $settings): void
            {
                $this->settings = clone $settings;
            }
        };

        $this->steps = new IntegrationSetupSteps(
            $this->base,
            $this->merchant,
            $this->business,
            $this->whatsapp,
            $this->datev,
            $this->payments,
            $this->ai,
            $this->routing,
        );
    }

    public function testStepsAreOrderedAndEndAfterDatev(): void
    {
        $this->assertSame(IntegrationSetupSteps::BASE_URL, IntegrationSetupSteps::STEPS[0]);
        $this->assertSame(IntegrationSetupSteps::ONLINE_PAYMENTS, IntegrationSetupSteps::next(IntegrationSetupSteps::BASE_URL));
        $this->assertSame(IntegrationSetupSteps::DATEV, IntegrationSetupSteps::STEPS[count(IntegrationSetupSteps::STEPS) - 1]);
        $this->assertNull(IntegrationSetupSteps::next(IntegrationSetupSteps::DATEV));
        $this->assertNull(IntegrationSetupSteps::next('unknown'));
        $this->assertFalse(IntegrationSetupSteps::isStep('unknown'));
    }

    public function testEveryStepHasPrefillValues(): void
    {
        foreach (IntegrationSetupSteps::STEPS as $step) {
            $this->assertNotSame([], $this->steps->currentValues($step), $step);
        }
    }

    public function testSavesBaseUrlAndRejectsInvalidOne(): void
    {
        $this->steps->save(IntegrationSetupSteps::BASE_URL, ['base_url' => 'https://verleih.example.de']);
        $this->assertSame('https://verleih.example.de', $this->base->get()->baseUrl);

        $this->expectException(ValidationException::class);
        $this->steps->save(IntegrationSetupSteps::BASE_URL, ['base_url' => 'keine url']);
    }

    public function testSavesGoogleMerchantSettings(): void
    {
        $this->steps->save(IntegrationSetupSteps::GOOGLE_MERCHANT, [
            'merchant_id' => ' 123456 ',
            'target_country' => 'at',
            'content_language' => 'DE',
            'product_link_template' => '',
        ]);

        $settings = $this->merchant->get();
        $this->assertSame('123456', $settings->merchantId);
        $this->assertSame('AT', $settings->targetCountry);
        $this->assertSame('de', $settings->contentLanguage);
        $this->assertNull($settings->productLinkTemplate);
    }

    public function testGoogleBusinessKeepsStoredSecretWhenLeftEmpty(): void
    {
        $this->business->save(new GoogleBusinessSettings(clientId: 'old', clientSecret: 'stored-secret'));

        $this->assertSame('', $this->steps->currentValues(IntegrationSetupSteps::GOOGLE_BUSINESS)['client_secret']);
        $this->assertTrue($this->steps->currentValues(IntegrationSetupSteps::GOOGLE_BUSINESS)['has_client_secret']);

        $this->steps->save(IntegrationSetupSteps::GOOGLE_BUSINESS, ['client_id' => 'new-id', 'client_secret' => '']);

        $this->assertSame('new-id', $this->business->get()->clientId);
        $this->assertSame('stored-secret', $this->business->get()->clientSecret);
    }

    public function testGoogleBusinessRequiresSecretOnFirstSetup(): void
    {
        $this->expectException(ValidationException::class);
        $this->steps->save(IntegrationSetupSteps::GOOGLE_BUSINESS, ['client_id' => 'id', 'client_secret' => '']);
    }

    public function testWhatsappKeepsStoredTokenWhenLeftEmpty(): void
    {
        $this->whatsapp->save(new WhatsappBusinessSettings(accessToken: 'stored-token'));

        $this->steps->save(IntegrationSetupSteps::WHATSAPP, ['access_token' => '', 'catalog_id' => '42', 'currency' => 'chf']);

        $settings = $this->whatsapp->get();
        $this->assertSame('stored-token', $settings->accessToken);
        $this->assertSame('42', $settings->catalogId);
        $this->assertSame('CHF', $settings->currency);
    }

    public function testDatevKeepsFieldsNotShownInInstaller(): void
    {
        $this->datev->save(new DatevSettings(includeReceipts: false, receiptTaxKey: '9', sendDay: 10));

        $this->steps->save(IntegrationSetupSteps::DATEV, [
            'datev_enabled' => '1',
            'datev_consultant_number' => '29098',
            'datev_client_number' => '55003',
            'datev_chart_of_accounts' => '04',
            'datev_account_length' => '4',
            'datev_fiscal_year_start_month' => '7',
            'datev_advisor_email' => 'kanzlei@example.de',
        ]);

        $settings = $this->datev->get();
        $this->assertTrue($settings->enabled);
        $this->assertSame(29098, $settings->consultantNumber);
        $this->assertSame(55003, $settings->clientNumber);
        $this->assertSame(DatevChartOfAccounts::Skr04, $settings->chartOfAccounts);
        $this->assertSame(7, $settings->fiscalYearStartMonth);
        $this->assertSame('kanzlei@example.de', $settings->advisorEmail);
        $this->assertFalse($settings->includeReceipts);
        $this->assertSame('9', $settings->receiptTaxKey);
        $this->assertSame(10, $settings->sendDay);
    }

    public function testDatevWithoutCheckboxIsDisabled(): void
    {
        $this->steps->save(IntegrationSetupSteps::DATEV, ['datev_chart_of_accounts' => '03']);

        $this->assertFalse($this->datev->get()->enabled);
    }

    public function testSavesOnlinePaymentsAndKeepsStoredSecrets(): void
    {
        $this->payments->save(new OnlinePaymentSettings(paypalClientSecret: 'stored-secret', sumupApiKey: 'stored-key'));

        $values = $this->steps->currentValues(IntegrationSetupSteps::ONLINE_PAYMENTS);
        $this->assertSame('', $values['paypal_client_secret']);
        $this->assertTrue($values['has_paypal_client_secret']);

        $this->steps->save(IntegrationSetupSteps::ONLINE_PAYMENTS, [
            'paypal_enabled' => '1',
            'paypal_client_id' => 'client',
            'paypal_client_secret' => '',
            'sumup_enabled' => '1',
            'sumup_api_key' => '',
            'sumup_merchant_code' => 'M123',
            'currency' => 'eur',
        ]);

        $settings = $this->payments->get();
        $this->assertTrue($settings->paypalEnabled);
        $this->assertFalse($settings->paypalSandbox);
        $this->assertSame('client', $settings->paypalClientId);
        $this->assertSame('stored-secret', $settings->paypalClientSecret);
        $this->assertSame('stored-key', $settings->sumupApiKey);
        $this->assertSame('M123', $settings->sumupMerchantCode);
        $this->assertTrue($settings->isPayPalUsable());
        $this->assertTrue($settings->isSumUpUsable());
    }

    public function testEnablingPayPalWithoutCredentialsFails(): void
    {
        $this->expectException(ValidationException::class);
        $this->steps->save(IntegrationSetupSteps::ONLINE_PAYMENTS, ['paypal_enabled' => '1', 'currency' => 'EUR']);
    }

    public function testSavesOpenAiKeyAndKeepsItWhenLeftEmpty(): void
    {
        // Empty prefill: the default image model keeps following AiAdImageSettings::DEFAULT_IMAGE_MODEL.
        $this->assertSame('', $this->steps->currentValues(IntegrationSetupSteps::AI)['image_model']);

        $this->steps->save(IntegrationSetupSteps::AI, ['openai_api_key' => 'sk-test', 'image_model' => '']);
        $this->assertSame('sk-test', $this->ai->get()->openaiApiKey);
        $this->assertNull($this->ai->get()->imageModel);

        $this->steps->save(IntegrationSetupSteps::AI, ['openai_api_key' => '', 'image_model' => 'gpt-image-1-mini']);
        $this->assertSame('sk-test', $this->ai->get()->openaiApiKey);
        $this->assertSame('gpt-image-1-mini', $this->ai->get()->imageModel);
        $this->assertTrue($this->steps->currentValues(IntegrationSetupSteps::AI)['has_openai_api_key']);
    }

    public function testSavesRoutingSettingsAndValidatesBuffer(): void
    {
        $this->steps->save(IntegrationSetupSteps::ROUTING, ['api_key' => 'ors-key', 'buffer_minutes' => '45']);
        $this->assertSame('ors-key', $this->routing->get()->apiKey);
        $this->assertSame(45, $this->routing->get()->bufferMinutes);

        $this->steps->save(IntegrationSetupSteps::ROUTING, ['api_key' => '', 'buffer_minutes' => '20']);
        $this->assertSame('ors-key', $this->routing->get()->apiKey);

        $this->expectException(ValidationException::class);
        $this->steps->save(IntegrationSetupSteps::ROUTING, ['api_key' => '', 'buffer_minutes' => '9999']);
    }
}
