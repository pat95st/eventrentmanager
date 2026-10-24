<?php

declare(strict_types=1);

namespace App\Application\Printing\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Printing\LabelPrinterRepositoryInterface;

final class UpdateLabelPrinterHandler implements CommandHandlerInterface
{
    use ValidatesLabelPrinterInput;

    public function __construct(private readonly LabelPrinterRepositoryInterface $printers)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateLabelPrinter);

        $printer = $this->printers->findById($command->id);
        if ($printer === null) {
            throw new NotFoundException("Etikettendrucker \"{$command->id}\" wurde nicht gefunden.");
        }

        [$errors, $data] = $this->validateLabelPrinterInput(
            $command->name,
            $command->driverType,
            $command->ipAddress,
            $command->port,
            $command->tapeWidthMm,
        );

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $printer->name = $data['name'];
        $printer->driverType = $data['driverType'];
        $printer->ipAddress = $data['ipAddress'];
        $printer->port = $data['port'];
        $printer->tapeWidthMm = $data['tapeWidthMm'];

        $this->printers->save($printer);

        return null;
    }
}
