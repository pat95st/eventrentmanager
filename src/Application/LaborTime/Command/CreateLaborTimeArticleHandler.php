<?php

declare(strict_types=1);

namespace App\Application\LaborTime\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\LaborTime\LaborTimeArticle;
use App\Domain\LaborTime\LaborTimeArticleRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

final class CreateLaborTimeArticleHandler implements CommandHandlerInterface
{
    public function __construct(private readonly LaborTimeArticleRepositoryInterface $laborTimeArticles)
    {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreateLaborTimeArticle);

        $name = trim($command->name);
        $errors = [];

        if ($name === '') {
            $errors['name'] = 'Bitte einen Namen angeben.';
        } elseif ($this->laborTimeArticles->existsByName($name)) {
            $errors['name'] = 'Dieser Arbeitszeit-Artikel existiert bereits.';
        }

        $hourlyRateValue = filter_var(str_replace(',', '.', trim($command->hourlyRate)), FILTER_VALIDATE_FLOAT);
        if ($hourlyRateValue === false || $hourlyRateValue <= 0) {
            $errors['hourlyRate'] = 'Bitte einen gültigen Stundensatz größer als 0 angeben.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $now = new DateTimeImmutable();
        $laborTimeArticle = new LaborTimeArticle(
            id: Uuid::v4(),
            name: $name,
            hourlyRate: number_format($hourlyRateValue, 2, '.', ''),
            createdAt: $now,
            updatedAt: $now,
        );

        $this->laborTimeArticles->save($laborTimeArticle);

        return $laborTimeArticle->id;
    }
}
