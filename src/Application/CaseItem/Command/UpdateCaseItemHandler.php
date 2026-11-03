<?php

declare(strict_types=1);

namespace App\Application\CaseItem\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\CaseItem\CaseItemRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;
use DateTimeImmutable;

final class UpdateCaseItemHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly CaseItemRepositoryInterface $cases,
        private readonly ManufacturerRepositoryInterface $manufacturers,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateCaseItem);

        $caseItem = $this->cases->findById($command->id);
        if ($caseItem === null) {
            throw new NotFoundException("Case \"{$command->id}\" wurde nicht gefunden.");
        }

        $errors = $this->validate($command);
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $caseItem->name = trim($command->name);
        $caseItem->manufacturerId = $command->manufacturerId;
        $caseItem->description = $command->description !== null && trim($command->description) !== '' ? trim($command->description) : null;
        $caseItem->updatedAt = new DateTimeImmutable();

        $this->cases->save($caseItem);

        return null;
    }

    /** @return array<string, string> */
    private function validate(UpdateCaseItem $command): array
    {
        $errors = [];

        if (trim($command->name) === '') {
            $errors['name'] = 'Bitte einen Namen angeben.';
        }

        if ($command->manufacturerId === '' || $this->manufacturers->findById($command->manufacturerId) === null) {
            $errors['manufacturerId'] = 'Bitte einen gültigen Hersteller auswählen.';
        }

        return $errors;
    }
}
