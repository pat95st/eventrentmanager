<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Settings\CompanySettings;
use App\Domain\Settings\CompanySettingsRepositoryInterface;
use App\Infrastructure\FileStorage\LocalFileStorage;

final class DeleteCompanyLogoHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly CompanySettingsRepositoryInterface $settingsRepository,
        private readonly LocalFileStorage $fileStorage,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteCompanyLogo);

        $current = $this->settingsRepository->get();

        if ($current->logoFilename !== null) {
            $this->fileStorage->delete('company', $current->logoFilename);
        }

        $settings = new CompanySettings(
            name: $current->name,
            street: $current->street,
            postalCode: $current->postalCode,
            city: $current->city,
            country: $current->country,
            logoFilename: null,
            // logoMaxHeightPx/darkLogoFilename/brandName hier unverändert
            // durchreichen — sonst würde das Löschen des Hell-Logos
            // ungewollt auch die Header-Höheneinstellung und das separat
            // verwaltete Dark-Mode-Logo zurücksetzen.
            logoMaxHeightPx: $current->logoMaxHeightPx,
            darkLogoFilename: $current->darkLogoFilename,
            brandName: $current->brandName,
            backgroundImageFilename: $current->backgroundImageFilename,
            phone: $current->phone,
            email: $current->email,
            website: $current->website,
            faviconFilename: $current->faviconFilename,
        );

        $this->settingsRepository->save($settings);

        return null;
    }
}
