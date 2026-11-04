<?php

declare(strict_types=1);

namespace App\Application\Rack\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;
use App\Domain\Numbering\NumberedDocumentType;
use App\Domain\Rack\RackExemplar;
use App\Domain\Rack\RackInventoryNumberGenerator;
use App\Domain\Rack\RackRepositoryInterface;
use App\Infrastructure\Persistence\Pdo\DocumentNumberSequence;
use App\Shared\Uuid;
use DateTimeImmutable;
use PDO;

/**
 * Legt ein Rack-Exemplar an (Migration 124):
 * - Inventurnummer wie bei Artikeln: Hersteller-Kürzel-Rack-Kürzel-0001
 *   (fortlaufend je Rack-Typ),
 * - Rack-Nummer fortlaufend über alle Racks: RACK-0001 … (Nummernkreis
 *   "Racks", Einstellungen › Nummerierung).
 * Beide Nummern werden in einer Transaktion vergeben; bereits vorhandene
 * Nummern werden übersprungen.
 */
final class AddRackExemplarHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly RackRepositoryInterface $racks,
        private readonly ManufacturerRepositoryInterface $manufacturers,
        private readonly RackValidator $validator,
        private readonly PDO $pdo,
        private readonly DocumentNumberSequence $numberSequence,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof AddRackExemplar);

        $rack = $this->racks->findById($command->rackId);
        if ($rack === null) {
            throw new NotFoundException('Rack-Typ wurde nicht gefunden.');
        }
        $manufacturer = $rack->manufacturerId !== null ? $this->manufacturers->findById($rack->manufacturerId) : null;
        if ($manufacturer?->prefix === null || $rack->prefix === null) {
            throw new ValidationException(['exemplar' => 'Für diesen Rack-Typ fehlt noch das Hersteller- und/oder Rack-Kürzel. Bitte zuerst beim Hersteller und beim Rack-Typ ein Kürzel hinterlegen.']);
        }
        $fixedId = $this->validator->validateFixedDevice($command->fixedInventoryNumberId, null);
        $acquisitionDate = self::date($command->acquisitionDate);

        $this->pdo->beginTransaction();
        try {
            $existing = $this->racks->findExemplars($rack->id);
            $now = new DateTimeImmutable();
            $inventoryNumber = RackInventoryNumberGenerator::nextNumber($existing, $manufacturer->prefix, $rack->prefix);
            while ($this->racks->existsExemplarByInventoryNumber($inventoryNumber)) {
                $existing[] = new RackExemplar('tmp', $rack->id, '', $inventoryNumber, null, null, $now, $now);
                $inventoryNumber = RackInventoryNumberGenerator::nextNumber($existing, $manufacturer->prefix, $rack->prefix);
            }
            do {
                $rackNumber = $this->numberSequence->allocateNext(NumberedDocumentType::Rack);
            } while ($this->racks->existsExemplarByRackNumber($rackNumber));

            $exemplar = new RackExemplar(Uuid::v4(), $rack->id, $rackNumber, $inventoryNumber, $fixedId, $acquisitionDate, $now, $now);
            $this->racks->saveExemplar($exemplar);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $exemplar->id;
    }

    public static function date(?string $raw): ?DateTimeImmutable
    {
        $raw = $raw !== null ? trim($raw) : '';
        if ($raw === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        if ($date === false) {
            throw new ValidationException(['acquisition_date' => 'Bitte ein gültiges Anschaffungsdatum angeben.']);
        }

        return $date;
    }
}
