<?php

declare(strict_types=1);

namespace App\Application\Rma\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\Rma\Rma;
use App\Domain\Rma\RmaKind;
use App\Domain\Rma\RmaReceiptRepositoryInterface;
use App\Domain\Rma\RmaRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

final class CreateRmaHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly RmaRepositoryInterface $rmas,
        private readonly InventoryNumberRepositoryInterface $inventoryNumbers,
        private readonly RmaReceiptRepositoryInterface $rmaReceipts,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreateRma);

        if ($this->inventoryNumbers->findById($command->inventoryNumberId) === null) {
            throw new NotFoundException("Inventurnummer \"{$command->inventoryNumberId}\" wurde nicht gefunden.");
        }

        $sentAt = $this->parseDate($command->sentAt);
        $returnedAt = $this->parseDate($command->returnedAt);
        $kind = RmaKind::tryFrom($command->kind) ?? RmaKind::Extern;

        $errors = [];
        $serviceProvider = null;
        $rmaReference = null;
        $workedHoursValue = null;
        $receiptIdsToPersist = [];

        if ($kind === RmaKind::Eigenreparatur) {
            $sentAt = null;

            $rawWorkedHours = trim((string) ($command->workedHours ?? ''));
            if ($rawWorkedHours !== '') {
                $parsed = filter_var(str_replace(',', '.', $rawWorkedHours), FILTER_VALIDATE_FLOAT);
                if ($parsed === false || $parsed < 0 || $parsed > 999.99) {
                    $errors['workedHours'] = 'Bitte eine gültige Arbeitszeit in Stunden angeben (0–999,99).';
                } else {
                    $workedHoursValue = number_format($parsed, 2, '.', '');
                }
            } else {
                $workedHoursValue = null;
            }

            $receiptIdsToPersist = array_values(array_unique(array_filter(
                $command->receiptIds,
                static fn ($id) => is_string($id) && $id !== ''
            )));
        } else {
            $serviceProvider = $this->nullIfBlank($command->serviceProvider);
            $rmaReference = $this->nullIfBlank($command->rmaReference);

            if ($returnedAt !== null && $sentAt === null) {
                $errors['returnedAt'] = 'Ohne Versanddatum kann noch kein Rücklieferdatum erfasst werden.';
            }
            if ($sentAt !== null && $returnedAt !== null && $returnedAt < $sentAt) {
                $errors['returnedAt'] = 'Die Rücklieferung kann nicht vor dem Versand liegen.';
            }

            $workedHoursValue = null;
            $receiptIdsToPersist = [];
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $now = new DateTimeImmutable();
        $rma = new Rma(
            id: Uuid::v4(),
            inventoryNumberId: $command->inventoryNumberId,
            kind: $kind,
            reason: $this->nullIfBlank($command->reason),
            serviceProvider: $serviceProvider,
            rmaReference: $rmaReference,
            sentAt: $sentAt,
            returnedAt: $returnedAt,
            notes: $this->nullIfBlank($command->notes),
            workedHours: $workedHoursValue,
            createdAt: $now,
            updatedAt: $now,
        );

        $this->rmas->save($rma);
        $this->rmaReceipts->replaceForRma($rma->id, $receiptIdsToPersist);

        return $rma->id;
    }

    private function parseDate(?string $value): ?DateTimeImmutable
    {
        return $value !== null && $value !== '' ? new DateTimeImmutable($value) : null;
    }

    private function nullIfBlank(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
