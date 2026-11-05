<?php

declare(strict_types=1);

namespace App\Application\Technician\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Technician\TechnicianSettings;
use App\Domain\Technician\TechnicianSettingsRepositoryInterface;

final class SaveTechnicianSettingsHandler implements CommandHandlerInterface
{
    public function __construct(private readonly TechnicianSettingsRepositoryInterface $settings)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof SaveTechnicianSettings);
        $hours = TechnicianSettings::parseHours($command->reminderHours);
        if ($hours === null) {
            throw new ValidationException(['reminderHours' => 'Bitte bis zu 10 ganze Stundenwerte zwischen 1 und 2160 angeben, getrennt durch Komma (z. B. 24, 72). Leer = keine Erinnerungen.']);
        }
        $this->settings->save(new TechnicianSettings($hours));

        return null;
    }
}
