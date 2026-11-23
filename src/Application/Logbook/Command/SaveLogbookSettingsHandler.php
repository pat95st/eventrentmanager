<?php

declare(strict_types=1);

namespace App\Application\Logbook\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Logbook\LogbookSettings;
use App\Domain\Logbook\LogbookSettingsRepositoryInterface;

/** Edit window: whole number of days, 0 (drivers cannot edit at all) .. 365. */
final class SaveLogbookSettingsHandler implements CommandHandlerInterface
{
    public function __construct(private readonly LogbookSettingsRepositoryInterface $settings)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof SaveLogbookSettings);

        $days = trim((string) $command->editWindowDays);
        if (preg_match('/^\d+$/', $days) !== 1 || (int) $days > LogbookSettings::MAX_EDIT_WINDOW_DAYS) {
            throw new ValidationException(['editWindowDays' => 'Bitte eine Anzahl Tage zwischen 0 und ' . LogbookSettings::MAX_EDIT_WINDOW_DAYS . ' angeben.']);
        }
        $settings = $this->settings->get();
        $settings->editWindowDays = (int) $days;
        $this->settings->save($settings);

        return null;
    }
}
