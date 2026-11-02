<?php

declare(strict_types=1);

namespace App\Application\Rack\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Rack\RackRepositoryInterface;

final class UpdateRackHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly RackRepositoryInterface $racks,
        private readonly RackValidator $validator,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateRack);
        $rack = $this->racks->findById($command->id);
        if ($rack === null) {
            throw new NotFoundException('Rack wurde nicht gefunden.');
        }
        $this->racks->save($this->validator->build($rack->id, $command->input, $rack));

        return null;
    }
}
