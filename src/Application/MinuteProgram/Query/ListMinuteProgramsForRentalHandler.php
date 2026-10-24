<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\MinuteProgram\MinuteProgramRepositoryInterface;

final class ListMinuteProgramsForRentalHandler implements QueryHandlerInterface
{
    public function __construct(private readonly MinuteProgramRepositoryInterface $programs)
    {
    }

    /** @return \App\Domain\MinuteProgram\MinuteProgram[] */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListMinuteProgramsForRental);

        return $this->programs->findByRental($query->rentalId);
    }
}
