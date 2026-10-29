<?php

declare(strict_types=1);

namespace App\Application\Article\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\Article;
use App\Domain\Article\ArticleBundleComponentRepositoryInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Rental\RentalBundleRepositoryInterface;
use App\Domain\Article\ProtectionClass;
use App\Domain\Category\CategoryRepositoryInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;
use App\Shared\Money;
use App\Shared\Prefix;
use App\Shared\Uuid;
use DateTimeImmutable;

final class CreateArticleHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly ArticleRepositoryInterface $articles,
        private readonly ManufacturerRepositoryInterface $manufacturers,
        private readonly CategoryRepositoryInterface $categories,
        private readonly ?ArticleBundleComponentRepositoryInterface $bundleComponents = null,
        private readonly ?RentalBundleRepositoryInterface $rentalBundles = null,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreateArticle);

        $errors = $this->validate($command);
        $documentShortText = $command->documentShortText !== null ? trim(preg_replace('/\s+/u', ' ', $command->documentShortText) ?? '') : '';
        if (mb_strlen($documentShortText) > 500) {
            $errors['documentShortText'] = 'Der Kurztext darf höchstens 500 Zeichen lang sein.';
        }

        [$bundleErrors, $components] = ValidatesBundleInput::validate(
            $command->isBundle, $command->isBillable, $command->dailyRentalPrice, $command->bundleComponents,
            null, false, $this->articles, $this->bundleComponents, $this->rentalBundles,
        );
        $errors = array_merge($errors, $bundleErrors);
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $now = new DateTimeImmutable();
        $article = new Article(
            id: Uuid::v4(),
            name: trim($command->name),
            manufacturerId: $command->manufacturerId,
            prefix: Prefix::normalize($command->prefix),
            categoryId: $command->categoryId,
            description: $command->description !== null && trim($command->description) !== '' ? trim($command->description) : null,
            weightValue: $command->weightValue !== null && $command->weightValue !== '' ? $command->weightValue : null,
            weightUnit: $command->weightUnit,
            dimensions: $command->dimensions !== null && trim($command->dimensions) !== '' ? trim($command->dimensions) : null,
            is19Inch: $command->is19Inch,
            heightUnits: $command->is19Inch ? self::normalizeHeightUnits($command->heightUnits) : null,
            installDepthCm: $command->is19Inch ? self::normalizeInstallDepth($command->installDepthCm) : null,
            purchasePrice: Money::normalize($command->purchasePrice) ?: null,
            requiresElectricalInspection: $command->requiresElectricalInspection,
            protectionClass: $command->requiresElectricalInspection ? self::normalizeProtectionClass($command->protectionClass) : null,
            hasFirmware: $command->hasFirmware,
            isBillable: $command->isBillable,
            isActive: true,
            dailyRentalPrice: $command->isBillable ? (Money::normalize($command->dailyRentalPrice) ?: null) : null,
            createdAt: $now,
            updatedAt: $now,
            // Ein Bundle ist immer selbst wählbar; "nicht einzeln vermietbar"
            // betrifft nur normale Artikel.
            isRentableIndividually: $command->isBundle || $command->isRentableIndividually,
            isBundle: $command->isBundle,
            documentShortText: $documentShortText !== '' ? $documentShortText : null,
        );

        $this->articles->save($article);
        if ($command->isBundle && $this->bundleComponents !== null) {
            $this->bundleComponents->replaceForBundle($article->id, $components);
        }

        return $article->id;
    }

    /** @return array<string, string> */
    private function validate(CreateArticle $command): array
    {
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
            && $this->articles->existsByPrefix($command->manufacturerId, $prefix)
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

        if ($command->requiresElectricalInspection && self::normalizeProtectionClass($command->protectionClass) === null) {
            $errors['protectionClass'] = 'Bitte eine gültige Schutzklasse auswählen.';
        }

        return $errors;
    }

    private static function normalizeProtectionClass(?string $raw): ?ProtectionClass
    {
        return $raw !== null ? ProtectionClass::tryFrom($raw) : null;
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
