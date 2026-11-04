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
 * Hintergrund hochladen: Bild (JPEG/PNG/WebP, max. 8 MB) oder kurzes Video
 * (MP4/WebM, max. 50 MB) — für die Oberfläche oder die Anmeldeseite.
 * Die vorherige Datei desselben Hintergrunds wird ersetzt.
 */
final class UploadCompanyBackgroundImageHandler implements CommandHandlerInterface
{
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
    private const VIDEO_EXTENSIONS = ['mp4', 'webm'];
    private const MAX_IMAGE_BYTES = 8 * 1024 * 1024;
    private const MAX_VIDEO_BYTES = 50 * 1024 * 1024;

    public function __construct(
        private readonly CompanySettingsRepositoryInterface $settingsRepository,
        private readonly LocalFileStorage $fileStorage,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UploadCompanyBackgroundImage);

        $file = $command->uploadedFile;
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new ValidationException(['background_image' => 'Die Datei ist größer, als der Server annimmt (PHP-Einstellung upload_max_filesize/post_max_size).']);
        }
        if ($error === UPLOAD_ERR_NO_FILE) {
            throw new ValidationException(['background_image' => 'Bitte eine Datei auswählen.']);
        }

        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $isVideo = in_array($extension, self::VIDEO_EXTENSIONS, true);
        if (!$isVideo && !in_array($extension, self::IMAGE_EXTENSIONS, true)) {
            throw new ValidationException(['background_image' => 'Erlaubt sind Bilder (JPEG, PNG, WebP) oder Videos (MP4, WebM).']);
        }

        $mime = $this->detectMime($file);
        if ($mime !== null && !str_starts_with($mime, $isVideo ? 'video/' : 'image/') && !($isVideo && $mime === 'application/mp4')) {
            throw new ValidationException(['background_image' => 'Der Dateiinhalt passt nicht zur Dateiendung.']);
        }

        if ($isVideo && ($file['size'] ?? 0) > self::MAX_VIDEO_BYTES) {
            throw new ValidationException(['background_image' => 'Das Hintergrundvideo darf höchstens 50 MB groß sein.']);
        }
        if (!$isVideo && ($file['size'] ?? 0) > self::MAX_IMAGE_BYTES) {
            throw new ValidationException(['background_image' => 'Das Hintergrundbild darf höchstens 8 MB groß sein.']);
        }

        $settings = $this->settingsRepository->get();
        $stored = $this->fileStorage->storeUploadedFile($file, 'company', Uuid::v4());

        $previous = $settings->backgroundFilename($command->slot);
        if ($previous !== null) {
            $this->fileStorage->delete('company', $previous);
        }

        $settings->setBackgroundFilename($command->slot, $stored['storedFilename']);
        $this->settingsRepository->save($settings);

        return null;
    }

    /** Tatsächlicher MIME-Typ der hochgeladenen Datei, falls ermittelbar. */
    private function detectMime(array $file): ?string
    {
        $path = (string) ($file['tmp_name'] ?? '');
        if ($path === '' || !is_file($path) || !function_exists('mime_content_type')) {
            return null;
        }
        $mime = mime_content_type($path);

        // Manche Systeme erkennen MP4/WebM nur als application/octet-stream.
        return $mime === false || $mime === 'application/octet-stream' ? null : $mime;
    }
}
