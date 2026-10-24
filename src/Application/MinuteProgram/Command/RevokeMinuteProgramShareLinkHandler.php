<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\MinuteProgram\MinuteProgramShareLinkRepositoryInterface;
use DateTimeImmutable;

final class RevokeMinuteProgramShareLinkHandler implements CommandHandlerInterface
{
    public function __construct(private readonly MinuteProgramShareLinkRepositoryInterface $shareLinks)
    {
    }

    public function handle(CommandInterface $command): void
    {
        assert($command instanceof RevokeMinuteProgramShareLink);

        $link = $this->shareLinks->findById($command->shareLinkId);
        if ($link === null) {
            throw new NotFoundException("Freigabe-Link \"{$command->shareLinkId}\" wurde nicht gefunden.");
        }

        $link->revokedAt ??= new DateTimeImmutable();

        $this->shareLinks->save($link);
    }
}
