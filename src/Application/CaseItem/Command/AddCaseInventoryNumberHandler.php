<?php

declare(strict_types=1);

namespace App\Application\CaseItem\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\CaseInventoryNumber\CaseInventoryNumber;
use App\Domain\CaseInventoryNumber\CaseInventoryNumberRepositoryInterface;
use App\Domain\CaseItem\CaseItemRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Numbering\NumberedDocumentType;
use App\Infrastructure\Persistence\Pdo\DocumentNumberSequence;
use App\Shared\Uuid;
use DateTimeImmutable;
use PDO;

/**
 * Legt ein Case-Exemplar an und vergibt die Inventurnummer automatisch und
 * fortlaufend über alle Cases (Nutzerwunsch, Migration 120): CASE-0001,
 * CASE-0002 … (Format unter Einstellungen › Nummerierung anpassbar).
 * Nummernvergabe und Speichern laufen in einer Transaktion, siehe
 * DocumentNumberSequence.
 */
final class AddCaseInventoryNumberHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly CaseInventoryNumberRepositoryInterface $caseInventoryNumbers,
        private readonly CaseItemRepositoryInterface $cases,
        private readonly PDO $pdo,
        private readonly DocumentNumberSequence $numberSequence,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof AddCaseInventoryNumber);

        $caseItem = $this->cases->findById($command->caseId);
        if ($caseItem === null) {
            throw new NotFoundException("Case \"{$command->caseId}\" wurde nicht gefunden.");
        }

        $acquisitionDate = $command->acquisitionDate !== null && $command->acquisitionDate !== ''
            ? new DateTimeImmutable($command->acquisitionDate)
            : null;

        $this->pdo->beginTransaction();
        try {
            // Nummern, die (z. B. von Hand) schon vergeben sind, überspringen.
            do {
                $number = $this->numberSequence->allocateNext(NumberedDocumentType::CaseItem);
            } while ($this->caseInventoryNumbers->existsByNumber($number));

            $now = new DateTimeImmutable();
            $caseInventoryNumber = new CaseInventoryNumber(
                id: Uuid::v4(),
                caseId: $caseItem->id,
                number: $number,
                acquisitionDate: $acquisitionDate,
                createdAt: $now,
                updatedAt: $now,
            );
            $this->caseInventoryNumbers->save($caseInventoryNumber);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $caseInventoryNumber->id;
    }
}
