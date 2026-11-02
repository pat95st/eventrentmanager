<?php

declare(strict_types=1);

namespace App\Application\Rack\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Rack\RackRepositoryInterface;

final class RemoveRackSlotHandler implements CommandHandlerInterface
{
    public function __construct(private readonly RackRepositoryInterface $racks)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof RemoveRackSlot);
        if ($this->racks->findSlotById($command->slotId) === null) {
            throw new NotFoundException('Einbau wurde nicht gefunden.');
        }
        $this->racks->deleteSlot($command->slotId);

        return null;
    }
}
