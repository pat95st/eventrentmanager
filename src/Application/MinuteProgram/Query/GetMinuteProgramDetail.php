<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Query;

use App\Application\Query\QueryInterface;

final class GetMinuteProgramDetail implements QueryInterface
{
    public function __construct(public readonly string $minuteProgramId)
    {
    }
}
