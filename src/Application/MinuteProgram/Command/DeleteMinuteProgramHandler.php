<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\MinuteProgram\MinuteProgramItemRepositoryInterface;
use App\Domain\MinuteProgram\MinuteProgramRepositoryInterface;
use App\Domain\MinuteProgram\MinuteProgramShareLinkRepositoryInterface;

final class DeleteMinuteProgramHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly MinuteProgramRepositoryInterface $programs,
        private readonly MinuteProgramItemRepositoryInterface $items,
        private readonly MinuteProgramShareLinkRepositoryInterface $shareLinks,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteMinuteProgram);

        $program = $this->programs->findById($command->minuteProgramId);
        if ($program === null) {
            throw new NotFoundException("Minutenprogramm \"{$command->minuteProgramId}\" wurde nicht gefunden.");
        }

        foreach ($this->items->findByMinuteProgram($program->id) as $item) {
            $this->items->delete($item->id);
        }

        foreach ($this->shareLinks->findByMinuteProgram($program->id) as $link) {
            // MinuteProgramShareLinkRepositoryInterface kennt kein delete(),
            // Freigabe-Links werden stattdessen revoziert, siehe
            // RevokeMinuteProgramShareLinkHandler. Beim Löschen des gesamten
            // Programms widerrufen wir aus Konsistenzgründen alle zugehörigen
            // Links, auch bereits widerrufene (idempotent).
            $link->revokedAt ??= new \DateTimeImmutable();
            $this->shareLinks->save($link);
        }

        $this->programs->delete($program->id);

        return null;
    }
}
