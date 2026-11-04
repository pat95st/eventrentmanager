<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
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

        // clone: alle übrigen Felder (auch später ergänzte) bleiben erhalten.
        $settings = clone $current;
        $settings->darkLogoFilename = null;

        $this->settingsRepository->save($settings);

        return null;
    }
}
