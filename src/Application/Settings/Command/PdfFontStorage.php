<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Domain\Exception\ConflictException;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\PdfFont;
use App\Domain\Settings\PdfFontRepositoryInterface;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Infrastructure\Pdf\TrueTypeFont;
use App\Shared\Uuid;
use DateTimeImmutable;

/** Legt Schriftdateien unter storage/uploads/fonts/<id>/ ab (gemeinsam für Google und Upload). */
final class PdfFontStorage
{
    public function __construct(
        private readonly PdfFontRepositoryInterface $fonts,
        private readonly LocalFileStorage $fileStorage,
    ) {
    }

    /** @param array<string, string> $bytesByStyle Schnitt => TTF-Bytes, "regular" Pflicht */
    public function store(string $family, string $source, array $bytesByStyle): PdfFont
    {
        $family = trim($family);
        if ($family === '') {
            throw new ValidationException(['family' => 'Bitte einen Namen für die Schrift angeben.']);
        }
        if ($this->fonts->findByFamily($family) !== null) {
            throw new ConflictException("Die Schrift „{$family}“ ist bereits vorhanden.");
        }
        if (!isset($bytesByStyle['regular'])) {
            throw new ValidationException(['regular' => 'Der normale Schnitt (Regular) ist Pflicht.']);
        }

        $id = Uuid::v4();
        $files = [];
        foreach (PdfFont::STYLES as $style) {
            if (!isset($bytesByStyle[$style])) {
                $files[$style] = null;
                continue;
            }
            $stored = $this->fileStorage->storeRawBytes($bytesByStyle[$style], 'fonts/' . $id, $style, $style . '.ttf', 'font/ttf');
            $files[$style] = $id . '/' . $stored['storedFilename'];
        }

        $font = new PdfFont($id, $family, $source, (string) $files['regular'], $files['bold'], $files['italic'], $files['boldItalic'], new DateTimeImmutable());
        $this->fonts->save($font);

        return $font;
    }

    /** Prüft, ob die Bytes eine einbettbare TrueType-Schrift sind. */
    public static function assertTrueType(string $bytes, string $field): void
    {
        try {
            TrueTypeFont::fromBytes($bytes);
        } catch (\RuntimeException $e) {
            throw new ValidationException([$field => $e->getMessage()]);
        }
    }

    public function deleteFiles(PdfFont $font): void
    {
        foreach ($font->files() as $file) {
            if ($file !== null) {
                try {
                    $this->fileStorage->delete('fonts', $file);
                } catch (\Throwable) {
                }
            }
        }
        @rmdir($this->fileStorage->absolutePath('fonts', $font->id));
    }
}
