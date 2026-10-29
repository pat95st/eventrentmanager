<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\CompanySettings;
use App\Domain\Settings\CompanySettingsRepositoryInterface;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Shared\Uuid;

/**
 * Spiegelt UploadCompanyLogoHandler exakt, nur für das optionale zweite Logo,
 * das speziell im Dunkelmodus der Oberfläche angezeigt wird (siehe
 * CompanyBranding::darkLogoUrl() und resources/views/layout.php). Wird NICHT
 * auf PDFs verwendet — dort bleibt es immer beim Hell-Logo.
 */
final class UploadCompanyDarkLogoHandler implements CommandHandlerInterface
{
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'];
    private const MAX_SIZE_BYTES = 4 * 1024 * 1024; // 4 MB

    public function __construct(
        private readonly CompanySettingsRepositoryInterface $settingsRepository,
        private readonly LocalFileStorage $fileStorage,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UploadCompanyDarkLogo);

        $file = $command->uploadedFile;

        if (!in_array($file['type'], self::ALLOWED_MIME_TYPES, true)) {
            throw new ValidationException(['darkLogo' => 'Nur JPEG-, PNG-, WebP-, GIF- oder SVG-Bilder sind erlaubt.']);
        }

        if ($file['size'] > self::MAX_SIZE_BYTES) {
            throw new ValidationException(['darkLogo' => 'Das Logo darf höchstens 4 MB groß sein.']);
        }

        $current = $this->settingsRepository->get();

        $stored = $this->fileStorage->storeUploadedFile($file, 'company', Uuid::v4());

        if ($current->darkLogoFilename !== null) {
            $this->fileStorage->delete('company', $current->darkLogoFilename);
        }

        $settings = new CompanySettings(
            name: $current->name,
            street: $current->street,
            postalCode: $current->postalCode,
            city: $current->city,
            country: $current->country,
            logoFilename: $current->logoFilename,
            logoMaxHeightPx: $current->logoMaxHeightPx,
            darkLogoFilename: $stored['storedFilename'],
            brandName: $current->brandName,
            backgroundImageFilename: $current->backgroundImageFilename,
            phone: $current->phone,
            email: $current->email,
            website: $current->website,
        );

        $this->settingsRepository->save($settings);

        return null;
    }
}
