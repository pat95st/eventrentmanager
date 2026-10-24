<?php

declare(strict_types=1);

namespace App\Application\Dunning\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Dunning\DunningFeeType;
use App\Domain\Dunning\DunningLevel;
use App\Domain\Dunning\DunningLevelRepositoryInterface;
use App\Domain\Exception\ValidationException;
use App\Shared\Uuid;
use DateTimeImmutable;

final class CreateDunningLevelHandler implements CommandHandlerInterface
{
    public function __construct(private readonly DunningLevelRepositoryInterface $dunningLevels)
    {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreateDunningLevel);

        [$name, $daysAfterDue, $feeType, $feeValue, $errors] = ValidatesDunningLevelInput::validate($command->name, $command->daysAfterDue, $command->feeType, $command->feeValue, $this->dunningLevels);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $now = new DateTimeImmutable();
        $level = new DunningLevel(
            id: Uuid::v4(),
            name: $name,
            daysAfterDue: $daysAfterDue,
            feeType: $feeType,
            feeValue: $feeValue,
            createdAt: $now,
            updatedAt: $now,
        );

        $this->dunningLevels->save($level);

        return $level->id;
    }
}
