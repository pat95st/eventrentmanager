<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
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

        $settings = $this->settingsRepository->get();
        $filename = $settings->backgroundFilename($command->slot);
        if ($filename !== null) {
            $this->fileStorage->delete('company', $filename);
        }

        $settings->setBackgroundFilename($command->slot, null);
        $this->settingsRepository->save($settings);

        return null;
    }
}
