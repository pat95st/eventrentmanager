<?php

declare(strict_types=1);

namespace App\Application\PowerPlan\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Category\CategoryRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\PowerPlan\ArticlePower;
use App\Domain\PowerPlan\RentalPowerPlanRepositoryInterface;

final class SetArticleInputPowerHandler implements CommandHandlerInterface
{
    public const MAX_WATTS = 500000;

    public function __construct(
        private readonly RentalPowerPlanRepositoryInterface $plans,
        private readonly ArticleRepositoryInterface $articles,
        private readonly CategoryRepositoryInterface $categories,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof SetArticleInputPower);
        $article = $this->articles->findById($command->articleId) ?? throw new NotFoundException('Artikel wurde nicht gefunden.');

        $isCable = $article->categoryId !== null && $article->categoryId === $this->categories->findCableCategoryId();
        $isDistributor = $article->categoryId !== null && $article->categoryId === $this->categories->findPowerDistributorCategoryId();
        if ($article->isBundle || $isCable || $isDistributor) {
            $this->plans->saveArticlePower($article->id, new ArticlePower(null, null));

            return null;
        }

        $errors = [];
        $peak = $this->parseWatts($command->watts, 'inputPower', 'Spitzenleistung', $errors);
        $average = $this->parseWatts($command->averageWatts, 'averagePower', 'durchschnittliche Leistung', $errors);
        if ($errors === [] && $peak !== null && $average !== null && $average > $peak) {
            $errors['averagePower'] = 'Die durchschnittliche Leistung darf die Spitzenleistung nicht übersteigen.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $this->plans->saveArticlePower($article->id, new ArticlePower($peak, $average));

        return null;
    }

    /**
     * @param array<string, string> $errors
     * @return int|null watts; null if empty or 0 (unknown)
     */
    private function parseWatts(?string $input, string $errorKey, string $label, array &$errors): ?int
    {
        $raw = trim(str_replace([' ', '.'], '', (string) $input));
        if ($raw === '') {
            return null;
        }
        if (!ctype_digit($raw) || (int) $raw > self::MAX_WATTS) {
            $errors[$errorKey] = 'Die ' . $label . ' muss eine ganze Zahl zwischen 0 und ' . number_format(self::MAX_WATTS, 0, ',', '.') . ' W sein.';

            return null;
        }

        return (int) $raw > 0 ? (int) $raw : null;
    }
}
