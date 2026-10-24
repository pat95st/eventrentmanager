<?php

declare(strict_types=1);

namespace App\Application\Dunning\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Dunning\DunningLevelRepositoryInterface;
use App\Domain\Exception\NotFoundException;

/**
 * Löscht eine Mahnstufe — bewusst OHNE Verwendungsprüfung (anders als z. B.
 * DeleteLaborTimeArticleHandler): bereits erstellte Mahnungen (DunningNotice)
 * haben Name/Fristtage/Gebühr vollständig auf sich selbst gesnapshotet (siehe
 * DunningLevel-Klassendoc) und referenzieren die Stufe nur noch lose
 * (dunning_level_id ON DELETE SET NULL) — ihre Löschung kann eine bestehende
 * Mahnung also nie inhaltlich verändern oder ungültig machen.
 */
final class DeleteDunningLevelHandler implements CommandHandlerInterface
{
    public function __construct(private readonly DunningLevelRepositoryInterface $dunningLevels)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteDunningLevel);

        $level = $this->dunningLevels->findById($command->id);
        if ($level === null) {
            throw new NotFoundException("Mahnstufe \"{$command->id}\" wurde nicht gefunden.");
        }

        $this->dunningLevels->delete($command->id);

        return null;
    }
}
