<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\MinuteProgram\MinuteProgramRepositoryInterface;

final class GetMinuteProgramDetailHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly MinuteProgramRepositoryInterface $programs,
        private readonly MinuteProgramDetailBuilder $detailBuilder,
    ) {
    }

    public function handle(QueryInterface $query): MinuteProgramDetail
    {
        assert($query instanceof GetMinuteProgramDetail);

        $program = $this->programs->findById($query->minuteProgramId);
        if ($program === null) {
            throw new NotFoundException("Minutenprogramm \"{$query->minuteProgramId}\" wurde nicht gefunden.");
        }

        return $this->detailBuilder->build($program);
    }
}
