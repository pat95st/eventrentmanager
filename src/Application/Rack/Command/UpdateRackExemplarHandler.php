<?php

declare(strict_types=1);

namespace App\Application\Rack\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Rack\RackRepositoryInterface;
use DateTimeImmutable;

final class UpdateRackExemplarHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly RackRepositoryInterface $racks,
        private readonly RackValidator $validator,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof UpdateRackExemplar);
        $exemplar = $this->racks->findExemplarById($command->exemplarId);
        if ($exemplar === null) {
            throw new NotFoundException('Rack-Exemplar wurde nicht gefunden.');
        }
        $exemplar->fixedInventoryNumberId = $this->validator->validateFixedDevice($command->fixedInventoryNumberId, $exemplar);
        $exemplar->acquisitionDate = AddRackExemplarHandler::date($command->acquisitionDate);
        $exemplar->updatedAt = new DateTimeImmutable();
        $this->racks->saveExemplar($exemplar);

        return $exemplar->rackId;
    }
}
