<?php

declare(strict_types=1);

namespace App\Application\Rack\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Rack\RackRepositoryInterface;

final class DeleteRackExemplarHandler implements CommandHandlerInterface
{
    public function __construct(private readonly RackRepositoryInterface $racks)
    {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof DeleteRackExemplar);
        $exemplar = $this->racks->findExemplarById($command->exemplarId);
        if ($exemplar === null) {
            throw new NotFoundException('Rack-Exemplar wurde nicht gefunden.');
        }
        $this->racks->deleteExemplar($exemplar->id);

        return $exemplar->rackId;
    }
}
