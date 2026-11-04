<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Settings\BackgroundStyle;
use App\Domain\Settings\CompanySettingsRepositoryInterface;

final class UpdateCompanyBackgroundStyleHandler implements CommandHandlerInterface
{
    public function __construct(private readonly CompanySettingsRepositoryInterface $settingsRepository)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateCompanyBackgroundStyle);

        $settings = $this->settingsRepository->get();
        // Werte außerhalb der erlaubten Bereiche begrenzt BackgroundStyle selbst.
        $settings->setBackgroundStyle($command->slot, new BackgroundStyle(
            opacity: $command->opacity,
            fit: $command->fit,
            positionX: $command->positionX,
            positionY: $command->positionY,
            zoom: $command->zoom,
        ));
        $this->settingsRepository->save($settings);

        return null;
    }
}
