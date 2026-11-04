<?php

declare(strict_types=1);

namespace App\Application\CaseItem\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\CaseInventoryNumber\CaseInventoryNumber;
use App\Domain\CaseInventoryNumber\CaseInventoryNumberGenerator;
use App\Domain\CaseInventoryNumber\CaseInventoryNumberRepositoryInterface;
use App\Domain\CaseItem\CaseItemRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;
use App\Domain\Numbering\NumberedDocumentType;
use App\Infrastructure\Persistence\Pdo\DocumentNumberSequence;
use App\Shared\Uuid;
use DateTimeImmutable;
use PDO;

/**
 * Legt ein Case-Exemplar an und vergibt zwei Nummern (Migration 124):
 * - Inventurnummer wie bei Artikeln: Hersteller-Kürzel-Case-Kürzel-0001
 *   (fortlaufend je Case-Modell, CaseInventoryNumberGenerator),
 * - Case-Nummer fortlaufend über alle Cases: CASE-0001 … (Nummernkreis
 *   "Cases", Einstellungen › Nummerierung).
 * Beides läuft in einer Transaktion; vorhandene Nummern werden übersprungen.
 */
final class AddCaseInventoryNumberHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly CaseInventoryNumberRepositoryInterface $caseInventoryNumbers,
        private readonly CaseItemRepositoryInterface $cases,
        private readonly PDO $pdo,
        private readonly DocumentNumberSequence $numberSequence,
        private readonly ?ManufacturerRepositoryInterface $manufacturers = null,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof AddCaseInventoryNumber);

        $caseItem = $this->cases->findById($command->caseId);
        if ($caseItem === null) {
            throw new NotFoundException("Case \"{$command->caseId}\" wurde nicht gefunden.");
        }

        $manufacturer = $this->manufacturers?->findById($caseItem->manufacturerId);
        if ($manufacturer?->prefix === null || $caseItem->prefix === null) {
            throw new ValidationException([
                'number' => 'Für dieses Case fehlt noch das Hersteller- und/oder Case-Kürzel. '
                    . 'Bitte zuerst beim Hersteller und beim Case ein Kürzel hinterlegen.',
            ]);
        }

        $acquisitionDate = $command->acquisitionDate !== null && $command->acquisitionDate !== ''
            ? new DateTimeImmutable($command->acquisitionDate)
            : null;

        $this->pdo->beginTransaction();
        try {
            $now = new DateTimeImmutable();
            $existing = $this->caseInventoryNumbers->findByCase($caseItem->id);
            $number = CaseInventoryNumberGenerator::nextNumber($existing, $manufacturer->prefix, $caseItem->prefix);
            while ($this->caseInventoryNumbers->existsByNumber($number)) {
                $existing[] = new CaseInventoryNumber('tmp', $caseItem->id, $number, null, $now, $now);
                $number = CaseInventoryNumberGenerator::nextNumber($existing, $manufacturer->prefix, $caseItem->prefix);
            }
            do {
                $caseNumber = $this->numberSequence->allocateNext(NumberedDocumentType::CaseItem);
            } while ($this->caseInventoryNumbers->existsByCaseNumber($caseNumber));

            $caseInventoryNumber = new CaseInventoryNumber(
                id: Uuid::v4(),
                caseId: $caseItem->id,
                number: $number,
                acquisitionDate: $acquisitionDate,
                createdAt: $now,
                updatedAt: $now,
                caseNumber: $caseNumber,
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
