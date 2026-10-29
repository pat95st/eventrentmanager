<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\PdfDesignSettings;
use App\Domain\Settings\PdfDesignSettingsRepositoryInterface;

/**
 * Leichte Validierung: accentColorHex muss ein gültiges "#rrggbb"-Hexformat
 * sein (wie es ein <input type="color"> immer liefert), logoPosition muss
 * einer von PdfDesignSettings::validLogoPositions() sein.
 */
final class UpdatePdfDesignSettingsHandler implements CommandHandlerInterface
{
    public function __construct(private readonly PdfDesignSettingsRepositoryInterface $settingsRepository)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdatePdfDesignSettings);

        $errors = [];

        $hex = trim($command->accentColorHex);
        if (preg_match('/^#([0-9a-fA-F]{6})$/', $hex, $matches) !== 1) {
            $errors['accentColorHex'] = 'Bitte eine gültige Farbe wählen.';
        }

        if (!in_array($command->logoPosition, PdfDesignSettings::validLogoPositions(), true)) {
            $errors['logoPosition'] = 'Bitte eine gültige Logo-Position wählen.';
        }

        if ($command->currencyDisplay !== null && !in_array($command->currencyDisplay, PdfDesignSettings::validCurrencyDisplays(), true)) {
            $errors['currencyDisplay'] = 'Bitte eine gültige Währungsangabe wählen.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $rgb = sscanf($matches[1], '%02x%02x%02x');

        $this->settingsRepository->save(new PdfDesignSettings(
            accentColorR: $rgb[0],
            accentColorG: $rgb[1],
            accentColorB: $rgb[2],
            logoPosition: $command->logoPosition,
            currencyDisplay: $command->currencyDisplay ?? $this->settingsRepository->get()->currencyDisplay,
        ));

        return null;
    }
}
