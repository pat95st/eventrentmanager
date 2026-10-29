<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Settings\CompanySettingsRepositoryInterface;
use App\Infrastructure\FileStorage\LocalFileStorage;

final class DeleteCompanyFaviconHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly CompanySettingsRepositoryInterface $settingsRepository,
        private readonly LocalFileStorage $fileStorage,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteCompanyFavicon);

        $settings = $this->settingsRepository->get();
        if ($settings->faviconFilename !== null) {
            $this->fileStorage->delete('company', $settings->faviconFilename);
            $settings->faviconFilename = null;
            $this->settingsRepository->save($settings);
        }

        return null;
    }
}
