<?php

declare(strict_types=1);

namespace App\Application\Technician\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\Technician\TechnicianNotifier;

/** @return array{email: bool, channels: string[], errors: string[]} */
final class NotifyTechnicianHandler implements CommandHandlerInterface
{
    public function __construct(private readonly TechnicianNotifier $notifier)
    {
    }

    public function handle(CommandInterface $command): array
    {
        assert($command instanceof NotifyTechnician);

        return $this->notifier->notify($command->id, $command->reminder);
    }
}
