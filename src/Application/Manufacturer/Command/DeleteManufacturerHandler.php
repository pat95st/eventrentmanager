<?php

declare(strict_types=1);

namespace App\Application\Manufacturer\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;

final class DeleteManufacturerHandler implements CommandHandlerInterface
{
    public function __construct(private readonly ManufacturerRepositoryInterface $manufacturers)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteManufacturer);

        $manufacturer = $this->manufacturers->findById($command->id);
        if ($manufacturer === null) {
            throw new NotFoundException("Hersteller \"{$command->id}\" wurde nicht gefunden.");
        }

        if ($this->manufacturers->isUsedByArticles($command->id)) {
            throw new ConflictException('Dieser Hersteller wird noch von Artikeln verwendet. Bitte zuerst einen anderen Hersteller zuweisen.');
        }

        if ($this->manufacturers->isUsedByCaseItems($command->id)) {
            throw new ConflictException('Dieser Hersteller wird noch von Cases verwendet. Bitte zuerst einen anderen Hersteller zuweisen.');
        }

        $this->manufacturers->delete($command->id);

        return null;
    }
}
