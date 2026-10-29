<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\CompanySettingsRepositoryInterface;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Shared\Uuid;

/**
 * Speichert ein eigenes Favicon (Migration 112). Erlaubt sind ICO, PNG und
 * SVG — geprüft über die Dateiendung, weil Browser .ico-Dateien mit sehr
 * unterschiedlichen MIME-Typen hochladen.
 */
final class UploadCompanyFaviconHandler implements CommandHandlerInterface
{
    private const ALLOWED_EXTENSIONS = ['ico', 'png', 'svg'];
    private const MAX_SIZE_BYTES = 1024 * 1024; // 1 MB

    public function __construct(
        private readonly CompanySettingsRepositoryInterface $settingsRepository,
        private readonly LocalFileStorage $fileStorage,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UploadCompanyFavicon);

        $file = $command->uploadedFile;
        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));

        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new ValidationException(['favicon' => 'Als Favicon sind nur ICO-, PNG- oder SVG-Dateien erlaubt.']);
        }

        if (($file['size'] ?? 0) > self::MAX_SIZE_BYTES) {
            throw new ValidationException(['favicon' => 'Das Favicon darf höchstens 1 MB groß sein.']);
        }

        $settings = $this->settingsRepository->get();
        $stored = $this->fileStorage->storeUploadedFile($file, 'company', Uuid::v4());

        if ($settings->faviconFilename !== null) {
            $this->fileStorage->delete('company', $settings->faviconFilename);
        }

        $settings->faviconFilename = $stored['storedFilename'];
        $this->settingsRepository->save($settings);

        return null;
    }
}
