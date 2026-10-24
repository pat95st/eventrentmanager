<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\MinuteProgram\MinuteProgramRepositoryInterface;
use App\Domain\MinuteProgram\MinuteProgramShareLinkRepositoryInterface;

/**
 * Löst einen Freigabe-Link-Token auf. Jede Anfrage über einen Freigabe-Link
 * löst den Token frisch auf, damit ein zwischenzeitlich widerrufener Link
 * sofort wirkt — es gibt bewusst keinen Session-/Cache-Zustand dazwischen.
 */
final class ResolveMinuteProgramShareLinkHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly MinuteProgramShareLinkRepositoryInterface $shareLinks,
        private readonly MinuteProgramRepositoryInterface $programs,
        private readonly MinuteProgramDetailBuilder $detailBuilder,
    ) {
    }

    public function handle(QueryInterface $query): ResolvedMinuteProgramShareLink
    {
        assert($query instanceof ResolveMinuteProgramShareLink);

        $link = $this->shareLinks->findByToken($query->token);
        if ($link === null || !$link->isActive()) {
            throw new NotFoundException('Dieser Freigabe-Link ist unbekannt oder wurde widerrufen.');
        }

        $program = $this->programs->findById($link->minuteProgramId);
        if ($program === null) {
            throw new NotFoundException("Minutenprogramm \"{$link->minuteProgramId}\" wurde nicht gefunden.");
        }

        return new ResolvedMinuteProgramShareLink($link, $this->detailBuilder->build($program));
    }
}
