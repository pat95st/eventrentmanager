<?php

declare(strict_types=1);

namespace App\Application\CaseItem\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\CaseItem\CaseItem;
use App\Domain\CaseItem\CaseItemRepositoryInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;
use App\Shared\Prefix;
use App\Shared\Uuid;
use DateTimeImmutable;

final class CreateCaseItemHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly CaseItemRepositoryInterface $cases,
        private readonly ManufacturerRepositoryInterface $manufacturers,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreateCaseItem);

        $errors = $this->validate($command);
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $now = new DateTimeImmutable();
        $caseItem = new CaseItem(
            id: Uuid::v4(),
            name: trim($command->name),
            manufacturerId: $command->manufacturerId,
            prefix: Prefix::normalize($command->prefix),
            description: $command->description !== null && trim($command->description) !== '' ? trim($command->description) : null,
            createdAt: $now,
            updatedAt: $now,
        );

        $this->cases->save($caseItem);

        return $caseItem->id;
    }

    /** @return array<string, string> */
    private function validate(CreateCaseItem $command): array
    {
        $errors = [];

        if (trim($command->name) === '') {
            $errors['name'] = 'Bitte einen Namen angeben.';
        }

        if ($command->manufacturerId === '' || $this->manufacturers->findById($command->manufacturerId) === null) {
            $errors['manufacturerId'] = 'Bitte einen gültigen Hersteller auswählen.';
        }

        $prefix = Prefix::normalize($command->prefix);
        if ($prefix === '') {
            $errors['prefix'] = 'Bitte ein Präfix angeben.';
        } elseif (!Prefix::isValid($prefix)) {
            $errors['prefix'] = 'Das Präfix darf nur aus Großbuchstaben und Ziffern bestehen (max. 10 Zeichen).';
        } elseif (
            $command->manufacturerId !== ''
            && $this->cases->existsByPrefix($command->manufacturerId, $prefix)
        ) {
            $errors['prefix'] = 'Dieses Präfix wird bei diesem Hersteller bereits von einem anderen Case verwendet.';
        }

        return $errors;
    }
}
