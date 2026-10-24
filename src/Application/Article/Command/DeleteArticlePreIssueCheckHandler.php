<?php

declare(strict_types=1);

namespace App\Application\Article\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ArticlePreIssueCheckRepositoryInterface;
use App\Domain\Exception\NotFoundException;

final class DeleteArticlePreIssueCheckHandler implements CommandHandlerInterface
{
    public function __construct(private readonly ArticlePreIssueCheckRepositoryInterface $preIssueChecks)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteArticlePreIssueCheck);

        $check = $this->preIssueChecks->findById($command->id);
        if ($check === null) {
            throw new NotFoundException("Vor-Ausgabe-Check \"{$command->id}\" wurde nicht gefunden.");
        }

        $this->preIssueChecks->delete($command->id);

        return null;
    }
}
