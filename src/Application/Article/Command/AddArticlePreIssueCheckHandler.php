<?php

declare(strict_types=1);

namespace App\Application\Article\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ArticlePreIssueCheck;
use App\Domain\Article\ArticlePreIssueCheckRepositoryInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Shared\Uuid;
use DateTimeImmutable;

final class AddArticlePreIssueCheckHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly ArticlePreIssueCheckRepositoryInterface $preIssueChecks,
        private readonly ArticleRepositoryInterface $articles,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof AddArticlePreIssueCheck);

        if ($this->articles->findById($command->articleId) === null) {
            throw new NotFoundException("Artikel \"{$command->articleId}\" wurde nicht gefunden.");
        }

        $description = trim($command->description);
        if ($description === '') {
            throw new ValidationException(['description' => 'Bitte eine Bezeichnung angeben.']);
        }

        $now = new DateTimeImmutable();
        $check = new ArticlePreIssueCheck(
            id: Uuid::v4(),
            articleId: $command->articleId,
            description: $description,
            createdAt: $now,
            updatedAt: $now,
        );

        $this->preIssueChecks->save($check);

        return $check->id;
    }
}
