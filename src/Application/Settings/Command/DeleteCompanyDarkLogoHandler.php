<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Settings\CompanySettings;
use App\Domain\Settings\CompanySettingsRepositoryInterface;
use App\Infrastructure\FileStorage\LocalFileStorage;

final class DeleteCompanyDarkLogoHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly CompanySettingsRepositoryInterface $settingsRepository,
        private readonly LocalFileStorage $fileStorage,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteCompanyDarkLogo);

        $current = $this->settingsRepository->get();

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
            darkLogoFilename: null,
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
