<?php

declare(strict_types=1);

namespace App\Application\Article\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use DateTimeImmutable;

/**
 * Aktiviert/deaktiviert einen Artikel — bewusst ein eigener, schlanker
 * Command statt über UpdateArticle (analog MarkReceiptPaid/MarkReceiptOpen):
 * das Ein-/Ausblenden aus der Artikelauswahl ist eine reine Status-Aktion,
 * unabhängig von den übrigen Stammdaten. Ein deaktivierter Artikel bleibt
 * in der Artikelverwaltung selbst vollständig sichtbar/bearbeitbar (siehe
 * ArticleController::index — dort wird ohne onlyActive-Filter gesucht) und
 * verschwindet nur aus der Artikelauswahl beim Erstellen von Angeboten/
 * Rechnungen/Veranstaltungspositionen/Case-Inhalten (siehe
 * ListArticlesHandler).
 */
final class SetArticleActiveHandler implements CommandHandlerInterface
{
    public function __construct(private readonly ArticleRepositoryInterface $articles)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof SetArticleActive);

        $article = $this->articles->findById($command->id);
        if ($article === null) {
            throw new NotFoundException("Artikel \"{$command->id}\" wurde nicht gefunden.");
        }

        $article->isActive = $command->isActive;
        $article->updatedAt = new DateTimeImmutable();
        $this->articles->save($article);

        return null;
    }
}
