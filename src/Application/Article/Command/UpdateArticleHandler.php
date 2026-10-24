<?php

declare(strict_types=1);

namespace App\Application\Article\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Category\CategoryRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;
use App\Shared\Money;
use App\Shared\Prefix;
use DateTimeImmutable;

final class UpdateArticleHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly ArticleRepositoryInterface $articles,
        private readonly ManufacturerRepositoryInterface $manufacturers,
        private readonly CategoryRepositoryInterface $categories,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateArticle);

        $article = $this->articles->findById($command->id);
        if ($article === null) {
            throw new NotFoundException("Artikel \"{$command->id}\" wurde nicht gefunden.");
        }

        $errors = [];

        if (trim($command->name) === '') {
            $errors['name'] = 'Bitte einen Produktnamen angeben.';
        }

        if ($command->manufacturerId === '' || $this->manufacturers->findById($command->manufacturerId) === null) {
            $errors['manufacturerId'] = 'Bitte einen gültigen Hersteller auswählen.';
        }

        $prefix = Prefix::normalize($command->prefix);
        if ($prefix === '') {
            $errors['prefix'] = 'Bitte ein Präfix angeben.';
        } elseif (!Prefix::isValid($prefix)) {
            $errors['prefix'] = 'Das Präfix darf nur aus Großbuchstaben und Ziffern bestehen (max. 10 Zeichen).';
        } elseif (
            $command->manufacturerId !== ''
            && $this->articles->existsByPrefix($command->manufacturerId, $prefix, $command->id)
        ) {
            $errors['prefix'] = 'Dieses Präfix wird bei diesem Hersteller bereits von einem anderen Artikel verwendet.';
        }

        if ($command->categoryId !== null && $this->categories->findById($command->categoryId) === null) {
            $errors['categoryId'] = 'Die gewählte Kategorie existiert nicht.';
        }

        if (Money::normalize($command->purchasePrice) === false) {
            $errors['purchasePrice'] = 'Bitte einen gültigen Einkaufspreis angeben (z. B. 199.99).';
        }

        if ($command->isBillable && Money::normalize($command->dailyRentalPrice) === false) {
            $errors['dailyRentalPrice'] = 'Bitte einen gültigen Preis pro Einsatztag angeben (z. B. 49.90).';
        }

        if ($command->is19Inch) {
            if (self::normalizeHeightUnits($command->heightUnits) === null) {
                $errors['heightUnits'] = 'Bitte eine gültige Anzahl an Höheneinheiten angeben (z. B. 3).';
            }

            if (self::normalizeInstallDepth($command->installDepthCm) === null) {
                $errors['installDepthCm'] = 'Bitte eine gültige Einbautiefe in cm angeben (z. B. 55.5).';
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $article->name = trim($command->name);
        $article->manufacturerId = $command->manufacturerId;
        $article->prefix = $prefix;
        $article->categoryId = $command->categoryId;
        $article->description = $command->description !== null && trim($command->description) !== '' ? trim($command->description) : null;
        $article->weightValue = $command->weightValue !== null && $command->weightValue !== '' ? $command->weightValue : null;
        $article->weightUnit = $command->weightUnit;
        $article->dimensions = $command->dimensions !== null && trim($command->dimensions) !== '' ? trim($command->dimensions) : null;
        $article->is19Inch = $command->is19Inch;
        $article->heightUnits = $command->is19Inch ? self::normalizeHeightUnits($command->heightUnits) : null;
        $article->installDepthCm = $command->is19Inch ? self::normalizeInstallDepth($command->installDepthCm) : null;
        $article->purchasePrice = Money::normalize($command->purchasePrice) ?: null;
        $article->requiresElectricalInspection = $command->requiresElectricalInspection;
        $article->hasFirmware = $command->hasFirmware;
        $article->isBillable = $command->isBillable;
        $article->dailyRentalPrice = $command->isBillable ? (Money::normalize($command->dailyRentalPrice) ?: null) : null;
        $article->updatedAt = new DateTimeImmutable();

        $this->articles->save($article);

        return null;
    }

    /** Höheneinheiten (HE): positive Ganzzahl, sonst null (= ungültig/leer). */
    private static function normalizeHeightUnits(?string $raw): ?int
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $value = filter_var(trim($raw), FILTER_VALIDATE_INT);

        return $value !== false && $value > 0 ? $value : null;
    }

    /** Einbautiefe in cm: positive Zahl (dt. Komma erlaubt), auf eine Nachkommastelle normalisiert, sonst null. */
    private static function normalizeInstallDepth(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $normalized = str_replace(',', '.', trim($raw));
        $value = filter_var($normalized, FILTER_VALIDATE_FLOAT);

        return $value !== false && $value > 0 ? number_format($value, 1, '.', '') : null;
    }
}
