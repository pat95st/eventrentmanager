<?php

declare(strict_types=1);

namespace App\Infrastructure\Federation;

use App\Application\Federation\LocalInstanceInterface;
use App\Domain\Federation\PartnerProfile;
use App\Domain\Settings\CompanySettingsRepositoryInterface;
use App\Domain\Settings\IntegrationBaseSettingsRepositoryInterface;

/**
 * This installation as seen by partners: name and address from the company
 * settings, base URL from settings › integrations (falls back to the host of
 * the current request, like the other integrations).
 */
final class LocalInstance implements LocalInstanceInterface
{
    public function __construct(
        private readonly CompanySettingsRepositoryInterface $companySettings,
        private readonly IntegrationBaseSettingsRepositoryInterface $baseSettings,
    ) {
    }

    public function baseUrl(): string
    {
        $configured = trim((string) $this->baseSettings->get()->baseUrl);
        if ($configured !== '') {
            return rtrim($configured, '/');
        }
        $https = ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off';

        return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }

    public function hasConfiguredBaseUrl(): bool
    {
        return trim((string) $this->baseSettings->get()->baseUrl) !== '';
    }

    public function name(): string
    {
        $settings = $this->companySettings->get();
        foreach ([$settings->name, $settings->brandName] as $candidate) {
            if ($candidate !== null && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return parse_url($this->baseUrl(), PHP_URL_HOST) ?: 'Materialverleih';
    }

    public function profile(): PartnerProfile
    {
        $settings = $this->companySettings->get();

        return new PartnerProfile($settings->street, $settings->postalCode, $settings->city, $settings->country, $settings->email, $settings->phone, $settings->website);
    }
}
