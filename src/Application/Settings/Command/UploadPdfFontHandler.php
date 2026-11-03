<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\PdfFont;

final class UploadPdfFontHandler implements CommandHandlerInterface
{
    private const MAX_SIZE_BYTES = 10 * 1024 * 1024;

    public function __construct(private readonly PdfFontStorage $storage)
    {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof UploadPdfFont);

        $family = preg_replace('/\s+/', ' ', trim($command->family)) ?? '';
        if ($family === '' || mb_strlen($family) > 150) {
            throw new ValidationException(['family' => 'Bitte einen Namen für die Schrift angeben.']);
        }

        $bytesByStyle = [];
        foreach (PdfFont::STYLES as $style) {
            $file = $command->files[$style] ?? null;
            if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
                throw new ValidationException([$style => 'Die Datei konnte nicht hochgeladen werden.']);
            }
            if ($file['size'] > self::MAX_SIZE_BYTES) {
                throw new ValidationException([$style => 'Die Schriftdatei darf höchstens 10 MB groß sein.']);
            }
            $bytes = (string) file_get_contents($file['tmp_name']);
            PdfFontStorage::assertTrueType($bytes, $style);
            $bytesByStyle[$style] = $bytes;
        }
        if (!isset($bytesByStyle['regular'])) {
            throw new ValidationException(['regular' => 'Bitte mindestens die normale Schriftdatei (Regular, .ttf) hochladen.']);
        }

        return $this->storage->store($family, 'upload', $bytesByStyle)->id;
    }
}
