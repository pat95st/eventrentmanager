<?php

declare(strict_types=1);

namespace App\Application\Purchase\Command;

use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Purchase\PurchaseTargetType;
use App\Domain\Supplier\SupplierRepositoryInterface;
use App\Shared\Money;

/**
 * Gemeinsame Validierung für Create-/UpdatePurchaseOrderHandler, analog zu
 * ValidatesOfferInput. Mindestens eine Position ist NICHT erzwungen (ein
 * Entwurf kann wie bei Offer/Rental zunächst leer angelegt werden).
 */
trait ValidatesPurchaseOrderInput
{
    /**
     * @param array<int, array{article_id: string, target_type: string, quantity: string, unit_price: string}> $rawItems
     * @return array{0: array<string, string>, 1: array{
     *     supplierId: string, note: ?string,
     *     items: array<int, array{articleId: string, targetType: PurchaseTargetType, orderedQuantity: int, unitPrice: string}>,
     * }}
     */
    private function validatePurchaseOrderInput(
        SupplierRepositoryInterface $suppliers,
        ArticleRepositoryInterface $articles,
        string $supplierId,
        string $note,
        array $rawItems,
    ): array {
        $errors = [];

        $supplierId = trim($supplierId);
        if ($supplierId === '') {
            $errors['supplierId'] = 'Bitte einen Lieferanten auswählen.';
        } elseif ($suppliers->findById($supplierId) === null) {
            $errors['supplierId'] = 'Der gewählte Lieferant wurde nicht gefunden.';
        }

        $items = [];
        foreach ($rawItems as $rawItem) {
            $articleId = trim((string) ($rawItem['article_id'] ?? ''));
            if ($articleId === '') {
                continue;
            }

            if ($articles->findById($articleId) === null) {
                $errors['items'] = 'Mindestens eine Position enthält einen unbekannten Artikel.';

                continue;
            }

            $targetType = PurchaseTargetType::tryFrom((string) ($rawItem['target_type'] ?? ''));
            if ($targetType === null) {
                $errors['items'] = 'Bitte für jede Position ein Ziel (Vermietpool/Verkaufsbestand) wählen.';

                continue;
            }

            $quantity = filter_var(trim((string) ($rawItem['quantity'] ?? '')), FILTER_VALIDATE_INT);
            if ($quantity === false || $quantity < 1) {
                $errors['items'] = 'Bitte für jede Position eine Menge von mindestens 1 angeben.';

                continue;
            }

            $unitPrice = Money::normalize((string) ($rawItem['unit_price'] ?? ''));
            if ($unitPrice === false || $unitPrice === null) {
                $errors['items'] = 'Bitte für jede Position einen gültigen Einkaufspreis angeben.';

                continue;
            }

            $items[] = [
                'articleId' => $articleId,
                'targetType' => $targetType,
                'orderedQuantity' => $quantity,
                'unitPrice' => $unitPrice,
            ];
        }

        return [$errors, [
            'supplierId' => $supplierId,
            'note' => trim($note) !== '' ? trim($note) : null,
            'items' => $items,
        ]];
    }
}
