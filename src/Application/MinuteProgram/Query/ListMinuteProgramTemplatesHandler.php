<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\MinuteProgram\MinuteProgramTemplateRepositoryInterface;

final class ListMinuteProgramTemplatesHandler implements QueryHandlerInterface
{
    public function __construct(private readonly MinuteProgramTemplateRepositoryInterface $templates)
    {
    }

    /** @return \App\Domain\MinuteProgram\MinuteProgramTemplate[] */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListMinuteProgramTemplates);

        return $this->templates->findAll();
    }
}
