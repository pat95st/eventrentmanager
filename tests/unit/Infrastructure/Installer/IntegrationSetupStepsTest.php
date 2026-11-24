<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Installer;

use App\Domain\Datev\DatevChartOfAccounts;
use App\Domain\Datev\DatevSettings;
use App\Domain\Datev\DatevSettingsRepositoryInterface;
use App\Domain\Exception\ValidationException;
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

final class IntegrationSetupStepsTest extends Unit
{
    private IntegrationBaseSettingsRepositoryInterface $base;
    private GoogleMerchantSettingsRepositoryInterface $merchant;
    private GoogleBusinessSettingsRepositoryInterface $business;
    private WhatsappBusinessSettingsRepositoryInterface $whatsapp;
    private DatevSettingsRepositoryInterface $datev;
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

        $this->steps = new IntegrationSetupSteps($this->base, $this->merchant, $this->business, $this->whatsapp, $this->datev);
    }

    public function testStepsAreOrderedAndEndAfterDatev(): void
    {
        $this->assertSame(IntegrationSetupSteps::BASE_URL, IntegrationSetupSteps::STEPS[0]);
        $this->assertSame(IntegrationSetupSteps::GOOGLE_MERCHANT, IntegrationSetupSteps::next(IntegrationSetupSteps::BASE_URL));
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
}
