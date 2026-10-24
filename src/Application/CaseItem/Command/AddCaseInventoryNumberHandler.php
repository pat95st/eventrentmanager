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
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Vergibt eine standardisierte Inventurnummer für ein Case-Exemplar nach dem
 * Schema (Hersteller-Präfix)-(Case-Präfix)-(laufende Nummer), analog zu
 * App\Application\Article\Command\AddInventoryNumberHandler für Artikel.
 */
final class AddCaseInventoryNumberHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly CaseInventoryNumberRepositoryInterface $caseInventoryNumbers,
        private readonly CaseItemRepositoryInterface $cases,
        private readonly ManufacturerRepositoryInterface $manufacturers,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof AddCaseInventoryNumber);

        $caseItem = $this->cases->findById($command->caseId);
        if ($caseItem === null) {
            throw new NotFoundException("Case \"{$command->caseId}\" wurde nicht gefunden.");
        }

        $manufacturer = $this->manufacturers->findById($caseItem->manufacturerId);
        if ($manufacturer === null) {
            throw new NotFoundException('Hersteller des Case wurde nicht gefunden.');
        }

        if ($manufacturer->prefix === null || $caseItem->prefix === null) {
            throw new ValidationException([
                'number' => 'Für dieses Case fehlt noch das Hersteller- und/oder Case-Präfix. '
                    . 'Bitte zuerst beim Hersteller und beim Case ein Präfix hinterlegen.',
            ]);
        }

        $existing = $this->caseInventoryNumbers->findByCase($caseItem->id);
        $number = CaseInventoryNumberGenerator::nextNumber($existing, $manufacturer->prefix, $caseItem->prefix);

        // Sicherheitsnetz gegen einen sehr unwahrscheinlichen Kollisionsfall.
        while ($this->caseInventoryNumbers->existsByNumber($number)) {
            $existing[] = new CaseInventoryNumber(
                id: 'temp',
                caseId: $caseItem->id,
                number: $number,
                acquisitionDate: null,
                createdAt: new DateTimeImmutable(),
                updatedAt: new DateTimeImmutable(),
            );
            $number = CaseInventoryNumberGenerator::nextNumber($existing, $manufacturer->prefix, $caseItem->prefix);
        }

        $acquisitionDate = $command->acquisitionDate !== null && $command->acquisitionDate !== ''
            ? new DateTimeImmutable($command->acquisitionDate)
            : null;

        $now = new DateTimeImmutable();
        $caseInventoryNumber = new CaseInventoryNumber(
            id: Uuid::v4(),
            caseId: $command->caseId,
            number: $number,
            acquisitionDate: $acquisitionDate,
            createdAt: $now,
            updatedAt: $now,
        );

        $this->caseInventoryNumbers->save($caseInventoryNumber);

        return $caseInventoryNumber->id;
    }
}
