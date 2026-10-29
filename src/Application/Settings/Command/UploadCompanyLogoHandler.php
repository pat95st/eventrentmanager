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

final class UploadCompanyLogoHandler implements CommandHandlerInterface
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
        assert($command instanceof UploadCompanyLogo);

        $file = $command->uploadedFile;

        if (!in_array($file['type'], self::ALLOWED_MIME_TYPES, true)) {
            throw new ValidationException(['logo' => 'Nur JPEG-, PNG-, WebP-, GIF- oder SVG-Bilder sind erlaubt.']);
        }

        if ($file['size'] > self::MAX_SIZE_BYTES) {
            throw new ValidationException(['logo' => 'Das Logo darf höchstens 4 MB groß sein.']);
        }

        $current = $this->settingsRepository->get();

        // Neuen Dateinamen verwenden (statt den alten zu überschreiben), damit
        // im Browser gecachte Vorschauen nicht versehentlich das alte Logo zeigen.
        $stored = $this->fileStorage->storeUploadedFile($file, 'company', Uuid::v4());

        if ($current->logoFilename !== null) {
            $this->fileStorage->delete('company', $current->logoFilename);
        }

        $settings = new CompanySettings(
            name: $current->name,
            street: $current->street,
            postalCode: $current->postalCode,
            city: $current->city,
            country: $current->country,
            logoFilename: $stored['storedFilename'],
            // Siehe DeleteCompanyLogoHandler: unverändert durchreichen, statt
            // stillschweigend auf den Standardwert zurückzufallen.
            logoMaxHeightPx: $current->logoMaxHeightPx,
            darkLogoFilename: $current->darkLogoFilename,
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
