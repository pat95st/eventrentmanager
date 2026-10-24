<?php

declare(strict_types=1);

namespace App\Application\Printing\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Printing\LabelPrinterRepositoryInterface;

final class DeleteLabelPrinterHandler implements CommandHandlerInterface
{
    public function __construct(private readonly LabelPrinterRepositoryInterface $printers)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteLabelPrinter);

        $printer = $this->printers->findById($command->id);
        if ($printer === null) {
            throw new NotFoundException("Etikettendrucker \"{$command->id}\" wurde nicht gefunden.");
        }

        $this->printers->delete($command->id);

        return null;
    }
}
