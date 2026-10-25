<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\PdfLayout\PdfLayoutBlueprint;
use App\Domain\Settings\PdfLayout\PdfLayoutElementPosition;
use App\Domain\Settings\PdfLayout\PdfLayoutSettingsRepositoryInterface;

/**
 * Speichert ALLE im Editor für einen Dokumenttyp gesetzten Block-
 * Positionen in einem Zug (ersetzt den bisherigen Stand vollständig,
 * siehe PdfLayoutSettingsRepositoryInterface::saveForDocumentType) —
 * der Editor sendet beim Speichern immer den kompletten aktuellen
 * Stand aller Blöcke dieses Dokumenttyps, nicht nur den zuletzt
 * gezogenen.
 */
final class UpdatePdfLayoutSettingsHandler implements CommandHandlerInterface
{
    private const MIN_MM = -50.0;
    private const MAX_MM = 500.0;

    public function __construct(private readonly PdfLayoutSettingsRepositoryInterface $repository)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdatePdfLayoutSettings);

        $allowedKeys = PdfLayoutBlueprint::allowedKeysFor($command->documentType);
        $errors = [];
        $positions = [];

        foreach ($command->positions as $elementKey => $raw) {
            if (!in_array($elementKey, $allowedKeys, true)) {
                // Unbekannter/nicht für diesen Dokumenttyp vorgesehener Block
                // -> ignorieren statt Fehler, damit ein veraltetes Editor-
                // Formular im Browser-Tab nicht die ganze Speicherung blockiert.
                continue;
            }

            $x = (float) ($raw['x'] ?? 0.0);
            $y = (float) ($raw['y'] ?? 0.0);

            if ($x < self::MIN_MM || $x > self::MAX_MM || $y < self::MIN_MM || $y > self::MAX_MM) {
                $errors[$elementKey] = 'Position außerhalb des zulässigen Bereichs.';

                continue;
            }

            $width = isset($raw['width']) && $raw['width'] !== null ? (float) $raw['width'] : null;
            $fontSize = isset($raw['fontSize']) && $raw['fontSize'] !== null ? (float) $raw['fontSize'] : null;

            if ($fontSize !== null && ($fontSize < 4.0 || $fontSize > 72.0)) {
                $errors[$elementKey] = 'Schriftgröße außerhalb des zulässigen Bereichs.';

                continue;
            }

            $positions[$elementKey] = new PdfLayoutElementPosition($x, $y, $width, $fontSize);
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $this->repository->saveForDocumentType($command->documentType, $positions);

        return null;
    }
}
