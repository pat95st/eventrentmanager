<?php

declare(strict_types=1);

namespace App\Application\ArticleSet\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\ArticleSet\ArticleSetRepositoryInterface;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Rental\RentalSetBookingRepositoryInterface;

/**
 * Löscht ein Set. In Veranstaltungen gebuchte Sets können nicht gelöscht
 * werden (stattdessen deaktivieren); Dokumentpositionen bleiben als
 * normale Freitext-Positionen erhalten (FK ON DELETE SET NULL).
 */
final class DeleteArticleSetHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly ArticleSetRepositoryInterface $sets,
        private readonly ?RentalSetBookingRepositoryInterface $bookings = null,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteArticleSet);

        if ($this->sets->findById($command->id) === null) {
            throw new NotFoundException("Set \"{$command->id}\" wurde nicht gefunden.");
        }
        if ($this->bookings?->existsForSet($command->id) ?? false) {
            throw new ConflictException('Dieses Set ist in Veranstaltungen gebucht und kann nicht gelöscht werden. Du kannst es stattdessen deaktivieren.');
        }

        $this->sets->delete($command->id);

        return null;
    }
}
