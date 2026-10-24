<?php

declare(strict_types=1);

namespace App\Application\Dunning\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Dunning\DunningLevelRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use DateTimeImmutable;

final class UpdateDunningLevelHandler implements CommandHandlerInterface
{
    public function __construct(private readonly DunningLevelRepositoryInterface $dunningLevels)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateDunningLevel);

        $level = $this->dunningLevels->findById($command->id);
        if ($level === null) {
            throw new NotFoundException("Mahnstufe \"{$command->id}\" wurde nicht gefunden.");
        }

        [$name, $daysAfterDue, $feeType, $feeValue, $errors] = ValidatesDunningLevelInput::validate($command->name, $command->daysAfterDue, $command->feeType, $command->feeValue, $this->dunningLevels, $command->id);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $level->name = $name;
        $level->daysAfterDue = $daysAfterDue;
        $level->feeType = $feeType;
        $level->feeValue = $feeValue;
        $level->updatedAt = new DateTimeImmutable();

        $this->dunningLevels->save($level);

        return null;
    }
}
