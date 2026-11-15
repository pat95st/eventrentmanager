<?php

declare(strict_types=1);

namespace App\Application\PowerPlan\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Category\CategoryRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
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

        $raw = trim(str_replace([' ', '.'], '', (string) $command->watts));
        $isCable = $article->categoryId !== null && $article->categoryId === $this->categories->findCableCategoryId();
        $isDistributor = $article->categoryId !== null && $article->categoryId === $this->categories->findPowerDistributorCategoryId();
        if ($raw === '' || $article->isBundle || $isCable || $isDistributor) {
            $this->plans->saveInputPower($article->id, null);

            return null;
        }
        if (!ctype_digit($raw) || (int) $raw > self::MAX_WATTS) {
            throw new ValidationException(['inputPower' => 'Die Eingangsleistung muss eine ganze Zahl zwischen 0 und ' . number_format(self::MAX_WATTS, 0, ',', '.') . ' W sein.']);
        }
        $this->plans->saveInputPower($article->id, (int) $raw > 0 ? (int) $raw : null);

        return null;
    }
}
