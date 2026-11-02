<?php

declare(strict_types=1);

namespace App\Application\Rack\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Rack\RackRepositoryInterface;
use App\Shared\Uuid;

final class CreateRackHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly RackRepositoryInterface $racks,
        private readonly RackValidator $validator,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreateRack);
        $rack = $this->validator->build(Uuid::v4(), $command->input, null);
        $this->racks->save($rack);

        return $rack->id;
    }
}
