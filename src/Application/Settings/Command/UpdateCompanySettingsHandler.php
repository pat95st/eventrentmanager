<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\CompanySettings;
use App\Domain\Settings\CompanySettingsRepositoryInterface;

final class UpdateCompanySettingsHandler implements CommandHandlerInterface
{
    private const LOGO_MAX_HEIGHT_MIN = 16;
    private const LOGO_MAX_HEIGHT_MAX = 200;

    public function __construct(private readonly CompanySettingsRepositoryInterface $settingsRepository)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateCompanySettings);

        $current = $this->settingsRepository->get();

        $logoMaxHeightPx = $this->parseLogoMaxHeightPx($command->logoMaxHeightPx);

        $settings = new CompanySettings(
            name: $this->nullIfEmpty($command->name),
            brandName: $this->nullIfEmpty($command->brandName),
            street: $this->nullIfEmpty($command->street),
            postalCode: $this->nullIfEmpty($command->postalCode),
            city: $this->nullIfEmpty($command->city),
            country: $this->nullIfEmpty($command->country),
            // Logo und Hintergrundbild werden über eigene Commands (UploadCompanyLogo/
            // DeleteCompanyLogo, UploadCompanyDarkLogo/DeleteCompanyDarkLogo,
            // UploadCompanyBackgroundImage/DeleteCompanyBackgroundImage) verwaltet,
            // damit ein reines Stammdaten-Speichern sie nicht versehentlich entfernt.
            logoFilename: $current->logoFilename,
            logoMaxHeightPx: $logoMaxHeightPx,
            darkLogoFilename: $current->darkLogoFilename,
            backgroundImageFilename: $current->backgroundImageFilename,
            phone: $command->phone === null ? $current->phone : $this->nullIfEmpty($command->phone),
            email: $command->email === null ? $current->email : $this->nullIfEmpty($command->email),
            website: $command->website === null ? $current->website : $this->nullIfEmpty($command->website),
            faviconFilename: $current->faviconFilename,
        );

        $this->settingsRepository->save($settings);

        return null;
    }

    private function nullIfEmpty(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
    }

    private function parseLogoMaxHeightPx(?string $value): ?int
    {
        $trimmed = $value === null ? '' : trim($value);

        if ($trimmed === '') {
            return null;
        }

        if (!ctype_digit($trimmed)) {
            throw new ValidationException(['logoMaxHeightPx' => 'Die maximale Logo-Höhe muss eine ganze Zahl in Pixeln sein.']);
        }

        $intValue = (int) $trimmed;

        if ($intValue < self::LOGO_MAX_HEIGHT_MIN || $intValue > self::LOGO_MAX_HEIGHT_MAX) {
            throw new ValidationException([
                'logoMaxHeightPx' => sprintf(
                    'Die maximale Logo-Höhe muss zwischen %d und %d Pixeln liegen.',
                    self::LOGO_MAX_HEIGHT_MIN,
                    self::LOGO_MAX_HEIGHT_MAX
                ),
            ]);
        }

        return $intValue;
    }
}
