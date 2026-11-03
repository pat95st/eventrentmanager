<?php

declare(strict_types=1);

namespace App\Application\Rack\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Numbering\NumberedDocumentType;
use App\Domain\Rack\RackRepositoryInterface;
use App\Infrastructure\Persistence\Pdo\DocumentNumberSequence;
use App\Shared\Uuid;
use PDO;

/**
 * Legt ein Rack an und vergibt die Inventarnummer automatisch und
 * fortlaufend (Nutzerwunsch, Migration 120): RACK-0001, RACK-0002 …
 * (Format unter Einstellungen › Nummerierung anpassbar). Nummernvergabe und
 * Speichern laufen in einer Transaktion, siehe DocumentNumberSequence.
 */
final class CreateRackHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly RackRepositoryInterface $racks,
        private readonly RackValidator $validator,
        private readonly PDO $pdo,
        private readonly DocumentNumberSequence $numberSequence,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreateRack);

        // Erst prüfen (ohne Nummer), damit ein Formularfehler keine Nummer verbraucht.
        $this->validator->build(Uuid::v4(), $command->input, null);

        $this->pdo->beginTransaction();
        try {
            do {
                $number = $this->numberSequence->allocateNext(NumberedDocumentType::Rack);
            } while ($this->racks->existsByInventoryNumber($number));

            $rack = $this->validator->build(Uuid::v4(), $command->input, null, $number);
            $this->racks->save($rack);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $rack->id;
    }
}
