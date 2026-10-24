<?php

declare(strict_types=1);

namespace App\Application\CaseItem\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\CaseContent\CaseContentItem;
use App\Domain\CaseContent\CaseContentItemRepositoryInterface;
use App\Domain\CaseInventoryNumber\CaseInventoryNumberRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Fügt einem Case-Exemplar einen Artikel (auf Artikel-Ebene, mit Menge) zur
 * "Packliste" hinzu. Ist der Artikel in diesem Case-Exemplar bereits
 * hinterlegt, wird die Menge auf die bestehende Zeile addiert statt eine
 * zweite Zeile für denselben Artikel anzulegen.
 */
final class AddCaseContentItemHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly CaseContentItemRepositoryInterface $contentItems,
        private readonly CaseInventoryNumberRepositoryInterface $caseInventoryNumbers,
        private readonly ArticleRepositoryInterface $articles,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof AddCaseContentItem);

        $caseInventoryNumber = $this->caseInventoryNumbers->findById($command->caseInventoryNumberId);
        if ($caseInventoryNumber === null) {
            throw new NotFoundException("Case-Inventurnummer \"{$command->caseInventoryNumberId}\" wurde nicht gefunden.");
        }

        $errors = [];

        $article = $this->articles->findById($command->articleId);
        if ($article === null) {
            $errors['articleId'] = 'Bitte einen gültigen Artikel auswählen.';
        }

        $quantity = filter_var($command->quantity, FILTER_VALIDATE_INT);
        if ($quantity === false || $quantity < 1) {
            $errors['quantity'] = 'Bitte eine Menge von mindestens 1 angeben.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $now = new DateTimeImmutable();

        $existingItems = $this->contentItems->findByCaseInventoryNumber($command->caseInventoryNumberId);
        foreach ($existingItems as $existing) {
            if ($existing->articleId === $command->articleId) {
                $existing->quantity += $quantity;
                $existing->updatedAt = $now;
                $this->contentItems->save($existing);

                return $existing->id;
            }
        }

        $item = new CaseContentItem(
            id: Uuid::v4(),
            caseInventoryNumberId: $command->caseInventoryNumberId,
            articleId: $command->articleId,
            quantity: $quantity,
            createdAt: $now,
            updatedAt: $now,
        );

        $this->contentItems->save($item);

        return $item->id;
    }
}
