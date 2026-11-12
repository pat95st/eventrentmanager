<?php

declare(strict_types=1);

namespace App\Application\Depreciation;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Category\CategoryRepositoryInterface;
use App\Domain\Depreciation\DepreciationFirstYearMode;
use App\Domain\Depreciation\DepreciationRepositoryInterface;
use App\Domain\Depreciation\DepreciationSettings;
use App\Domain\Exception\ValidationException;
use App\Shared\AmountInput;

final class UpdateDepreciationSettingsHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly DepreciationRepositoryInterface $depreciation,
        private readonly CategoryRepositoryInterface $categories,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateDepreciationSettings);

        $errors = [];
        $max = DepreciationSettings::MAX_USEFUL_LIFE_YEARS;
        $yearsError = 'Bitte eine Nutzungsdauer zwischen 1 und ' . $max . ' Jahren angeben.';

        $defaultYears = $this->years($command->defaultUsefulLifeYears);
        if ($defaultYears === null) {
            $errors['default_useful_life_years'] = $yearsError;
        }

        $threshold = $this->amount($command->lowValueThreshold, 'low_value_threshold', $errors);
        $residual = $this->amount($command->residualValue, 'residual_value', $errors);

        $mode = DepreciationFirstYearMode::tryFrom($command->firstYearMode);
        if ($mode === null) {
            $errors['first_year_mode'] = 'Bitte eine gültige Auswahl treffen.';
        }

        $known = [];
        foreach ($this->categories->findAll() as $category) {
            $known[$category->id] = true;
        }
        $categoryYears = [];
        foreach ($command->categoryYears as $categoryId => $input) {
            if (trim($input) === '' || !isset($known[$categoryId])) {
                continue;
            }
            $years = $this->years($input);
            if ($years === null) {
                $errors['category_years'] = $yearsError;

                continue;
            }
            $categoryYears[(string) $categoryId] = $years;
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $this->depreciation->saveSettings(new DepreciationSettings((int) $defaultYears, $threshold, $mode, $residual));
        $this->depreciation->replaceCategoryUsefulLives($categoryYears);

        return null;
    }

    private function years(string $input): ?int
    {
        $input = trim($input);
        if (!ctype_digit($input) || (int) $input < 1 || (int) $input > DepreciationSettings::MAX_USEFUL_LIFE_YEARS) {
            return null;
        }

        return (int) $input;
    }

    /** @param array<string, string> $errors */
    private function amount(string $input, string $field, array &$errors): string
    {
        try {
            return AmountInput::normalize($input, 99999999.99) ?? '0.00';
        } catch (\InvalidArgumentException) {
            $errors[$field] = 'Bitte einen gültigen Betrag (0 oder mehr) angeben.';

            return '0.00';
        }
    }
}
