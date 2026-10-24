<?php

declare(strict_types=1);

namespace App\Application\LaborTime\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\LaborTime\LaborTimeArticleRepositoryInterface;

final class DeleteLaborTimeArticleHandler implements CommandHandlerInterface
{
    public function __construct(private readonly LaborTimeArticleRepositoryInterface $laborTimeArticles)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteLaborTimeArticle);

        $laborTimeArticle = $this->laborTimeArticles->findById($command->id);
        if ($laborTimeArticle === null) {
            throw new NotFoundException("Arbeitszeit-Artikel \"{$command->id}\" wurde nicht gefunden.");
        }

        if ($this->laborTimeArticles->isUsedByOfferItems($command->id)) {
            throw new ConflictException('Dieser Arbeitszeit-Artikel wird noch von einem Angebot verwendet. Bitte zuerst die entsprechende Position entfernen.');
        }

        if ($this->laborTimeArticles->isUsedByInvoiceItems($command->id)) {
            throw new ConflictException('Dieser Arbeitszeit-Artikel wird noch von einer Rechnung verwendet. Bitte zuerst die entsprechende Position entfernen.');
        }

        $this->laborTimeArticles->delete($command->id);

        return null;
    }
}
