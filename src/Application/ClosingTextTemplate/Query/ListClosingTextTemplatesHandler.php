<?php

declare(strict_types=1);

namespace App\Application\ClosingTextTemplate\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\ClosingTextTemplate\ClosingTextTemplate;
use App\Domain\ClosingTextTemplate\ClosingTextTemplateRepositoryInterface;

final class ListClosingTextTemplatesHandler implements QueryHandlerInterface
{
    public function __construct(private readonly ClosingTextTemplateRepositoryInterface $templates)
    {
    }

    /** @return ClosingTextTemplate[] Alle Textvorlagen, alphabetisch — die Zuordnung zu Ordnern wird in der View aus folderId gebaut. */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListClosingTextTemplates);

        return $this->templates->findAll();
    }
}
