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

/** Analog zu UploadCompanyLogoHandler, siehe dort für die Begründung der einzelnen Schritte. */
final class UploadCompanyBackgroundImageHandler implements CommandHandlerInterface
{
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    private const MAX_SIZE_BYTES = 8 * 1024 * 1024; // 8 MB — Hintergrundbilder sind i. d. R. größer als ein Logo

    public function __construct(
        private readonly CompanySettingsRepositoryInterface $settingsRepository,
        private readonly LocalFileStorage $fileStorage,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UploadCompanyBackgroundImage);

        $file = $command->uploadedFile;

        if (!in_array($file['type'], self::ALLOWED_MIME_TYPES, true)) {
            throw new ValidationException(['background_image' => 'Nur JPEG-, PNG- oder WebP-Bilder sind erlaubt.']);
        }

        if ($file['size'] > self::MAX_SIZE_BYTES) {
            throw new ValidationException(['background_image' => 'Das Hintergrundbild darf höchstens 8 MB groß sein.']);
        }

        $current = $this->settingsRepository->get();

        $stored = $this->fileStorage->storeUploadedFile($file, 'company', Uuid::v4());

        if ($current->backgroundImageFilename !== null) {
            $this->fileStorage->delete('company', $current->backgroundImageFilename);
        }

        $settings = new CompanySettings(
            name: $current->name,
            street: $current->street,
            postalCode: $current->postalCode,
            city: $current->city,
            country: $current->country,
            logoFilename: $current->logoFilename,
            logoMaxHeightPx: $current->logoMaxHeightPx,
            darkLogoFilename: $current->darkLogoFilename,
            brandName: $current->brandName,
            backgroundImageFilename: $stored['storedFilename'],
        );

        $this->settingsRepository->save($settings);

        return null;
    }
}
