<?php

declare(strict_types=1);

namespace App\Application\ClosingTextTemplate\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\ClosingTextTemplate\ClosingTextTemplateFolder;
use App\Domain\ClosingTextTemplate\ClosingTextTemplateFolderRepositoryInterface;

final class ListClosingTextTemplateFoldersHandler implements QueryHandlerInterface
{
    public function __construct(private readonly ClosingTextTemplateFolderRepositoryInterface $folders)
    {
    }

    /** @return ClosingTextTemplateFolder[] Alle Ordner flach, alphabetisch — der Baum wird in der View aus parentId gebaut. */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListClosingTextTemplateFolders);

        return $this->folders->findAll();
    }
}
