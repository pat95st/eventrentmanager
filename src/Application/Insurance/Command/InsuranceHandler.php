<?php

declare(strict_types=1);

namespace App\Application\Insurance\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\Insurance\Estimation\CurrentValueEstimate;
use App\Application\Insurance\Estimation\CurrentValueEstimationException;
use App\Application\Insurance\Estimation\CurrentValueEstimationRequest;
use App\Application\Insurance\Estimation\CurrentValueEstimatorInterface;
use App\Application\Insurance\Query\GetRentalInsuranceList;
use App\Application\Insurance\Query\GetRentalInsuranceListHandler;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Category\CategoryRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Insurance\CurrentValueRepositoryInterface;
use App\Domain\Insurance\CurrentValueResolver;
use App\Domain\Insurance\InsuranceField;
use App\Domain\InventoryNumber\InventoryNumber;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;
use DateTimeImmutable;

/**
 * Zeitwerte per KI schätzen (gespeichert an der Inventarnummer), feste
 * Artikel-Zeitwerte pflegen und Exportspalten merken (Migration 144).
 */
final class InsuranceHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly CurrentValueRepositoryInterface $currentValues,
        private readonly InventoryNumberRepositoryInterface $inventoryNumbers,
        private readonly ArticleRepositoryInterface $articles,
        private readonly ManufacturerRepositoryInterface $manufacturers,
        private readonly CategoryRepositoryInterface $categories,
        private readonly CurrentValueEstimatorInterface $estimator,
        private readonly GetRentalInsuranceListHandler $insuranceList,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        return match (true) {
            $command instanceof SetArticleFixedCurrentValue => $this->setArticleFixedValue($command),
            $command instanceof SaveInsuranceExportFields => $this->saveExportFields($command),
            $command instanceof EstimateCurrentValue => $this->estimateOne($command->inventoryNumberId),
            $command instanceof EstimateMissingCurrentValues => $this->estimateMissing($command),
            default => throw new \LogicException('Unbekannter Befehl ' . $command::class),
        };
    }

    private function setArticleFixedValue(SetArticleFixedCurrentValue $command): mixed
    {
        if ($this->articles->findById($command->articleId) === null) {
            throw new NotFoundException('Artikel wurde nicht gefunden.');
        }
        $this->currentValues->setArticleFixedValue($command->articleId, $this->amount($command->value, 'fixed_current_value'));

        return null;
    }

    private function saveExportFields(SaveInsuranceExportFields $command): mixed
    {
        $fields = InsuranceField::fromList($command->fields);
        if ($fields === []) {
            throw new ValidationException(['fields' => 'Bitte mindestens eine Spalte für den Export auswählen.']);
        }
        $this->currentValues->saveExportFields($fields);

        return null;
    }

    /** Schätzt und speichert den Zeitwert an der Inventarnummer; liefert die aktualisierte Inventarnummer. */
    private function estimateOne(string $inventoryNumberId): InventoryNumber
    {
        $this->assertEstimatorAvailable();
        $request = $this->estimationRequest($inventoryNumberId);
        $estimate = $this->estimator->estimate($request);

        return $this->storeEstimate($inventoryNumberId, $estimate);
    }

    private function storeEstimate(string $inventoryNumberId, CurrentValueEstimate $estimate): InventoryNumber
    {
        $inventoryNumber = $this->inventoryNumbers->findById($inventoryNumberId);
        if ($inventoryNumber === null) {
            throw new NotFoundException('Inventarnummer wurde nicht gefunden.');
        }
        $inventoryNumber->setCurrentValue($estimate->value, InventoryNumber::CURRENT_VALUE_AI, $estimate->reasoning);
        $inventoryNumber->updatedAt = new DateTimeImmutable();
        $this->inventoryNumbers->save($inventoryNumber);

        return $inventoryNumber;
    }

    private function estimateMissing(EstimateMissingCurrentValues $command): EstimationSummary
    {
        $this->assertEstimatorAvailable();
        $list = $this->insuranceList->handle(new GetRentalInsuranceList($command->rentalId, includeUnissued: false));

        $estimated = 0;
        $failed = 0;
        $remaining = 0;
        $requests = 0;
        $errors = [];
        /** @var array<string, CurrentValueEstimate> $cache gleichartige Exemplare nur einmal anfragen */
        $cache = [];

        foreach ($list->rows as $row) {
            if ($row->currentValue !== null || $row->inventoryNumberId === null) {
                continue;
            }
            try {
                $request = $this->estimationRequest($row->inventoryNumberId);
                $key = $request->cacheKey();
                if (!isset($cache[$key])) {
                    if ($requests >= $command->maxRequests) {
                        $remaining++;
                        continue;
                    }
                    $requests++;
                    $cache[$key] = $this->estimator->estimate($request);
                }
                $this->storeEstimate($row->inventoryNumberId, $cache[$key]);
                $estimated++;
            } catch (CurrentValueEstimationException | NotFoundException $e) {
                $failed++;
                $errors[$e->getMessage()] = ($row->inventoryNumber ?? $row->manufacturerModel) . ': ' . $e->getMessage();
            }
        }

        return new EstimationSummary($estimated, $failed, $remaining, array_values($errors));
    }

    private function estimationRequest(string $inventoryNumberId): CurrentValueEstimationRequest
    {
        $inventoryNumber = $this->inventoryNumbers->findById($inventoryNumberId);
        if ($inventoryNumber === null) {
            throw new NotFoundException('Inventarnummer wurde nicht gefunden.');
        }
        $article = $this->articles->findById($inventoryNumber->articleId);
        if ($article === null) {
            throw new NotFoundException('Artikel der Inventarnummer wurde nicht gefunden.');
        }
        $manufacturer = $article->manufacturerId !== null ? $this->manufacturers->findById($article->manufacturerId) : null;
        $category = $article->categoryId !== null ? $this->categories->findById($article->categoryId) : null;
        $description = trim((string) ($article->documentShortText ?: $article->description));

        return new CurrentValueEstimationRequest(
            category: $category?->name,
            manufacturer: $manufacturer?->name,
            model: $article->name,
            description: $description !== '' ? mb_substr(strip_tags($description), 0, 400) : null,
            acquisitionDate: $inventoryNumber->acquisitionDate,
            purchasePrice: $inventoryNumber->effectivePurchasePrice($article->purchasePrice),
            today: new DateTimeImmutable('today'),
        );
    }

    private function assertEstimatorAvailable(): void
    {
        if (!$this->estimator->isAvailable()) {
            throw new CurrentValueEstimationException('Für die KI-Schätzung ist kein OpenAI-API-Schlüssel hinterlegt (Einstellungen › KI-Werbebilder).');
        }
    }

    private function amount(?string $value, string $field): ?string
    {
        try {
            return CurrentValueResolver::normalizeAmount($value);
        } catch (\InvalidArgumentException) {
            throw new ValidationException([$field => 'Bitte einen gültigen Zeitwert in Euro angeben (z. B. 149,90).']);
        }
    }
}
