<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Settings\CompanySettings;
use App\Domain\Settings\CompanySettingsRepositoryInterface;
use App\Infrastructure\FileStorage\LocalFileStorage;

final class DeleteCompanyBackgroundImageHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly CompanySettingsRepositoryInterface $settingsRepository,
        private readonly LocalFileStorage $fileStorage,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteCompanyBackgroundImage);

        $current = $this->settingsRepository->get();

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
            backgroundImageFilename: null,
            phone: $current->phone,
            email: $current->email,
            website: $current->website,
            faviconFilename: $current->faviconFilename,
        );

        $this->settingsRepository->save($settings);

        return null;
    }
}
