<?php

declare(strict_types=1);

namespace App\Application\Rack\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Rack\RackRepositoryInterface;

/** Eingebaute Geräte bleiben erhalten, nur ihre Zuordnung zum Rack entfällt (FK CASCADE). */
final class DeleteRackHandler implements CommandHandlerInterface
{
    public function __construct(private readonly RackRepositoryInterface $racks)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteRack);
        if ($this->racks->findById($command->id) === null) {
            throw new NotFoundException('Rack wurde nicht gefunden.');
        }
        $this->racks->delete($command->id);

        return null;
    }
}
