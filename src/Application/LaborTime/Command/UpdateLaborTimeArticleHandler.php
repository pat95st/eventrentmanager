<?php

declare(strict_types=1);

namespace App\Application\LaborTime\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\LaborTime\LaborTimeArticleRepositoryInterface;
use DateTimeImmutable;

final class UpdateLaborTimeArticleHandler implements CommandHandlerInterface
{
    public function __construct(private readonly LaborTimeArticleRepositoryInterface $laborTimeArticles)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateLaborTimeArticle);

        $laborTimeArticle = $this->laborTimeArticles->findById($command->id);
        if ($laborTimeArticle === null) {
            throw new NotFoundException("Arbeitszeit-Artikel \"{$command->id}\" wurde nicht gefunden.");
        }

        $name = trim($command->name);
        $errors = [];

        if ($name === '') {
            $errors['name'] = 'Bitte einen Namen angeben.';
        } elseif ($this->laborTimeArticles->existsByName($name, $command->id)) {
            $errors['name'] = 'Dieser Arbeitszeit-Artikel existiert bereits.';
        }

        $hourlyRateValue = filter_var(str_replace(',', '.', trim($command->hourlyRate)), FILTER_VALIDATE_FLOAT);
        if ($hourlyRateValue === false || $hourlyRateValue <= 0) {
            $errors['hourlyRate'] = 'Bitte einen gültigen Stundensatz größer als 0 angeben.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $laborTimeArticle->name = $name;
        $laborTimeArticle->hourlyRate = number_format($hourlyRateValue, 2, '.', '');
        $laborTimeArticle->updatedAt = new DateTimeImmutable();

        $this->laborTimeArticles->save($laborTimeArticle);

        return null;
    }
}
