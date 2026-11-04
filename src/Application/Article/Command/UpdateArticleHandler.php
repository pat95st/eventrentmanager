<?php

declare(strict_types=1);

namespace App\Application\Article\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ArticleBundleComponentRepositoryInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Rental\RentalBundleRepositoryInterface;
use App\Domain\Article\ProtectionClass;
use App\Domain\Category\CategoryRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;
use App\Application\Article\BundleWeightCalculator;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Shared\Money;
use App\Shared\Prefix;
use DateTimeImmutable;

final class UpdateArticleHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly ArticleRepositoryInterface $articles,
        private readonly ManufacturerRepositoryInterface $manufacturers,
        private readonly CategoryRepositoryInterface $categories,
        private readonly ?ArticleBundleComponentRepositoryInterface $bundleComponents = null,
        private readonly ?RentalBundleRepositoryInterface $rentalBundles = null,
        private readonly ?InventoryNumberRepositoryInterface $inventoryNumbers = null,
        // Stromverteiler (Migration 117): Elektroprüfung immer erforderlich.
        private readonly ?\App\Domain\PowerDistributor\PowerDistributorRepositoryInterface $powerDistributors = null,
        private readonly ?\App\Domain\Cable\CableRepositoryInterface $cables = null,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateArticle);

        // Leitungen: feste Regeln (generischer Hersteller, immer Zubehör, Elektroprüfung nach Leitungstyp, …).
        $cableRulesCategoryId = $this->cables !== null ? $this->categories->findCableCategoryId() : null;
        $isCableArticle = !$command->isBundle && $cableRulesCategoryId !== null && $command->categoryId === $cableRulesCategoryId;
        if ($isCableArticle) {
            $command = (new \App\Application\Cable\CableArticleRules($this->manufacturers, $this->cables))->apply($command);
        }

        $article = $this->articles->findById($command->id);
        if ($article === null) {
            throw new NotFoundException("Artikel \"{$command->id}\" wurde nicht gefunden.");
        }

        $errors = [];

        if (trim($command->name) === '') {
            $errors['name'] = 'Bitte einen Produktnamen angeben.';
        }

        // Bundles haben keinen Hersteller und kein Präfix (keine
        // Inventarnummern), siehe Migration 111.
        if (!$command->isBundle && ($command->manufacturerId === '' || $this->manufacturers->findById($command->manufacturerId) === null)) {
            $errors['manufacturerId'] = 'Bitte einen gültigen Hersteller auswählen.';
        }

        $prefix = Prefix::normalize($command->prefix);
        if ($command->isBundle) {
            // keine Prüfung
        } elseif ($prefix === '') {
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

        if ($command->is19Inch && !$command->isBundle) {
            if (self::normalizeHeightUnits($command->heightUnits) === null) {
                $errors['heightUnits'] = 'Bitte eine gültige Anzahl an Höheneinheiten angeben (z. B. 3).';
            }

            if (self::normalizeInstallDepth($command->installDepthCm) === null) {
                $errors['installDepthCm'] = 'Bitte eine gültige Einbautiefe in cm angeben (z. B. 55.5).';
            }
        }

        if ($command->powerDistributor === null && !$command->requiresElectricalInspection && $this->powerDistributors?->findByArticle($article->id) !== null) {
            $errors['protectionClass'] = 'Dieser Artikel ist ein Stromverteiler — die Elektroprüfung ist immer erforderlich.';
        }

        if ($command->requiresElectricalInspection && !$command->isBundle && self::normalizeProtectionClass($command->protectionClass) === null) {
            $errors['protectionClass'] = 'Bitte eine gültige Schutzklasse auswählen.';
        }

        $documentShortText = $command->documentShortText !== null ? trim(preg_replace('/\s+/u', ' ', $command->documentShortText) ?? '') : '';
        if (mb_strlen($documentShortText) > 500) {
            $errors['documentShortText'] = 'Der Kurztext darf höchstens 500 Zeichen lang sein.';
        }

        [$bundleErrors, $components] = ValidatesBundleInput::validate(
            $command->isBundle, $command->isBillable, $command->dailyRentalPrice, $command->bundleComponents,
            $article->id, $article->isBundle, $this->articles, $this->bundleComponents, $this->rentalBundles,
            hasInventoryNumbers: $command->isBundle && !$article->isBundle && $this->inventoryNumbers !== null && $this->inventoryNumbers->findByArticle($article->id) !== [],
        );
        $errors = array_merge($errors, $bundleErrors);

        // Stromverteiler (Migration 117): Angaben aus dem Artikelformular
        // mitprüfen; Elektroprüfung dann immer erforderlich (Standard SK I).
        $distributor = null;
        $requiresElectricalInspection = $command->requiresElectricalInspection;
        $protectionClass = $command->requiresElectricalInspection ? self::normalizeProtectionClass($command->protectionClass) : null;
        // Stromverteiler = Artikel der Kategorie "Stromverteiler" (Migration 118).
        $powerDistributorCategoryId = $this->powerDistributors !== null ? $this->categories->findPowerDistributorCategoryId() : null;
        $isPowerDistributor = !$command->isBundle && $powerDistributorCategoryId !== null && $command->categoryId === $powerDistributorCategoryId;
        if ($isPowerDistributor && $command->powerDistributor instanceof \App\Application\PowerDistributor\PowerDistributorInput) {
            [$distributorErrors, $distributor] = (new \App\Application\PowerDistributor\PowerDistributorBuilder())->build(
                $article->id,
                $command->powerDistributor,
                $this->powerDistributors?->findByArticle($article->id),
            );
            $errors = array_merge($errors, $distributorErrors);
            unset($errors['protectionClass']);
            $requiresElectricalInspection = true;
            $protectionClass = self::normalizeProtectionClass($command->protectionClass) ?? ProtectionClass::I;
        }

        // Leitungen (Migration 125): Artikel der Kategorie "Leitungen" bestehen aus
        // Leitungstyp, Länge und zwei passenden Steckverbindern. Ohne Namen wird
        // er daraus gebildet.
        $cable = null;
        $cableType = null;
        $cableCategoryId = $this->cables !== null ? $this->categories->findCableCategoryId() : null;
        $isCable = !$command->isBundle && $cableCategoryId !== null && $command->categoryId === $cableCategoryId;
        if ($isCable && ($command->cable !== null || false)) {
            [$cableErrors, $cable, $cableType] = (new \App\Application\Cable\CableBuilder($this->cables))->build($article->id, $command->cable ?? new \App\Application\Cable\CableInput(null, null, null, null));
            $errors = array_merge($errors, $cableErrors);
            unset($errors['name']);
        }
        $articleName = trim($command->name);
        if ($articleName === '' && $cable !== null && $cableType !== null) {
            $articleName = \App\Application\Cable\CableBuilder::suggestName($cableType, $cable, $this->cables->findConnectorById($cable->connectorAId), $this->cables->findConnectorById($cable->connectorBId));
        }

        if ($isCableArticle) {
            unset($errors['protectionClass']); // Leitungen haben keine Schutzklasse
            if ($command->manufacturerId === '') {
                $errors['manufacturerId'] = 'Für Leitungen wird der Hersteller „Generisch“ benötigt – bitte unter „Hersteller“ prüfen.';
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $article->name = $articleName;
        $article->manufacturerId = $command->manufacturerId;
        $article->prefix = $prefix;
        $article->categoryId = $command->categoryId;
        $article->description = $command->description !== null && trim($command->description) !== '' ? trim($command->description) : null;
        $article->weightValue = $command->weightValue !== null && $command->weightValue !== '' ? $command->weightValue : null;
        $article->weightUnit = $command->weightUnit;
        $article->dimensions = $command->dimensions !== null && trim($command->dimensions) !== '' ? trim($command->dimensions) : null;
        $article->is19Inch = $command->is19Inch;
        if (!$command->is19Inch) {
            $article->rackWidth = \App\Domain\Rack\RackWidth::Full;
        } elseif ($command->rackWidth !== null) {
            $article->rackWidth = \App\Domain\Rack\RackWidth::tryFrom($command->rackWidth) ?? \App\Domain\Rack\RackWidth::Full;
        }
        $article->heightUnits = $command->is19Inch ? self::normalizeHeightUnits($command->heightUnits) : null;
        $article->installDepthCm = $command->is19Inch ? self::normalizeInstallDepth($command->installDepthCm) : null;
        $article->purchasePrice = Money::normalize($command->purchasePrice) ?: null;
        $article->requiresElectricalInspection = $requiresElectricalInspection;
        $article->protectionClass = $protectionClass;
        $article->hasFirmware = $command->hasFirmware;
        $article->isBillable = $command->isBillable;
        $article->dailyRentalPrice = $command->isBillable ? (Money::normalize($command->dailyRentalPrice) ?: null) : null;
        $article->isRentableIndividually = $command->isBundle || $command->isRentableIndividually;
        $article->isBundle = $command->isBundle;
        if ($command->documentShortText !== null) {
            $article->documentShortText = $documentShortText !== '' ? $documentShortText : null;
        }
        $article->updatedAt = new DateTimeImmutable();
        if ($command->isBundle) {
            $this->applyBundleRules($article, $components);
        }

        $this->articles->save($article);
        if ($this->powerDistributors !== null) {
            if ($distributor !== null) {
                $this->powerDistributors->save($distributor);
            } elseif ($command->powerDistributor === false || $command->isBundle || ($command->powerDistributor !== null && !$isPowerDistributor)) {
                // Kategorie gewechselt, Bundle oder ausdrücklich kein Stromverteiler mehr.
                $this->powerDistributors->delete($article->id);
            }
        }
        if ($this->cables !== null) {
            if ($cable !== null) {
                $this->cables->saveArticleCable($cable);
            } elseif ($command->isBundle || ($command->cable !== null && !$isCable)) {
                // Kategorie gewechselt oder Bundle: keine Leitung mehr.
                $this->cables->deleteArticleCable($article->id);
            }
        }
        if ($this->bundleComponents !== null) {
            // Kein Bundle (mehr) → Bestandteil-Liste leeren.
            $this->bundleComponents->replaceForBundle($article->id, $command->isBundle ? $components : []);
        }

        // Gewicht der Bundles nachziehen, in denen dieser Artikel steckt.
        if (!$command->isBundle) {
            (new BundleWeightCalculator($this->articles, $this->bundleComponents))->recalculateBundlesContaining($article->id);
        }

        return null;
    }

    /**
     * Bundles (Migration 111): kein Hersteller, kein Präfix, keine
     * Elektroprüfung, keine Firmware, keine 19-Zoll-Angaben; Gewicht aus
     * den Bestandteilen berechnet.
     *
     * @param \App\Domain\Article\ArticleBundleComponent[] $components
     */
    private function applyBundleRules(\App\Domain\Article\Article $article, array $components): void
    {
        $article->manufacturerId = null;
        $article->prefix = null;
        $article->requiresElectricalInspection = false;
        $article->protectionClass = null;
        $article->hasFirmware = false;
        $article->is19Inch = false;
        $article->rackWidth = \App\Domain\Rack\RackWidth::Full;
        $article->heightUnits = null;
        $article->installDepthCm = null;
        (new BundleWeightCalculator($this->articles))->apply($article, $components);
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

    private static function normalizeProtectionClass(?string $raw): ?ProtectionClass
    {
        return $raw !== null ? ProtectionClass::tryFrom($raw) : null;
    }
}
