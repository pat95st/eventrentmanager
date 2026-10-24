<?php

declare(strict_types=1);

namespace App\Application\Printing\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Printing\LabelPrinter;
use App\Domain\Printing\LabelPrinterRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

final class CreateLabelPrinterHandler implements CommandHandlerInterface
{
    use ValidatesLabelPrinterInput;

    public function __construct(private readonly LabelPrinterRepositoryInterface $printers)
    {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreateLabelPrinter);

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

        $printer = new LabelPrinter(
            id: Uuid::v4(),
            name: $data['name'],
            driverType: $data['driverType'],
            ipAddress: $data['ipAddress'],
            port: $data['port'],
            tapeWidthMm: $data['tapeWidthMm'],
            createdAt: new DateTimeImmutable(),
        );

        $this->printers->save($printer);

        return $printer->id;
    }
}
