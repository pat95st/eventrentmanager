<?php

declare(strict_types=1);

namespace App\Application\Rack\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Rack\RackRepositoryInterface;
use App\Domain\Rack\RackSlot;
use App\Shared\Uuid;
use DateTimeImmutable;

final class AddRackSlotHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly RackRepositoryInterface $racks,
        private readonly RackValidator $validator,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof AddRackSlot);
        $exemplar = $this->racks->findExemplarById($command->exemplarId);
        $rack = $exemplar !== null ? $this->racks->findById($exemplar->rackId) : null;
        if ($exemplar === null || $rack === null) {
            throw new NotFoundException('Rack wurde nicht gefunden.');
        }
        $start = $this->validator->validateSlot($rack, $exemplar, $command->inventoryNumberId, $command->startUnit);
        $slot = new RackSlot(Uuid::v4(), $exemplar->id, $command->inventoryNumberId, $start, new DateTimeImmutable());
        $this->racks->saveSlot($slot);

        return $slot->id;
    }
}
