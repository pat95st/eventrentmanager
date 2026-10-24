<?php

declare(strict_types=1);

namespace App\Application\Article\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ArticlePreIssueCheckRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use DateTimeImmutable;

final class UpdateArticlePreIssueCheckHandler implements CommandHandlerInterface
{
    public function __construct(private readonly ArticlePreIssueCheckRepositoryInterface $preIssueChecks)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateArticlePreIssueCheck);

        $check = $this->preIssueChecks->findById($command->id);
        if ($check === null) {
            throw new NotFoundException("Vor-Ausgabe-Check \"{$command->id}\" wurde nicht gefunden.");
        }

        $description = trim($command->description);
        if ($description === '') {
            throw new ValidationException(['description' => 'Bitte eine Bezeichnung angeben.']);
        }

        $check->description = $description;
        $check->updatedAt = new DateTimeImmutable();

        $this->preIssueChecks->save($check);

        return null;
    }
}
