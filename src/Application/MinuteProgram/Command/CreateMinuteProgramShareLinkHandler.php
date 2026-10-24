<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\MinuteProgram\MinuteProgramRepositoryInterface;
use App\Domain\MinuteProgram\MinuteProgramShareLink;
use App\Domain\MinuteProgram\MinuteProgramShareLinkRepositoryInterface;
use App\Domain\MinuteProgram\ShareLinkMode;
use App\Shared\Uuid;
use DateTimeImmutable;

final class CreateMinuteProgramShareLinkHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly MinuteProgramShareLinkRepositoryInterface $shareLinks,
        private readonly MinuteProgramRepositoryInterface $programs,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreateMinuteProgramShareLink);

        if ($this->programs->findById($command->minuteProgramId) === null) {
            throw new NotFoundException("Minutenprogramm \"{$command->minuteProgramId}\" wurde nicht gefunden.");
        }

        $mode = ShareLinkMode::tryFrom($command->mode);
        if ($mode === null) {
            throw new ValidationException(['mode' => 'Ungültiger Freigabe-Modus.']);
        }

        // Token wird bewusst im Klartext gespeichert (bestehendes
        // Projekt-Konvention, siehe Kundencheckliste-Token) und ist allein
        // durch seine Länge/Zufälligkeit geschützt — kein Session-Login.
        $token = bin2hex(random_bytes(24));

        $link = new MinuteProgramShareLink(
            id: Uuid::v4(),
            minuteProgramId: $command->minuteProgramId,
            token: $token,
            mode: $mode,
            createdAt: new DateTimeImmutable(),
            revokedAt: null,
        );

        $this->shareLinks->save($link);

        return $link->id;
    }
}
