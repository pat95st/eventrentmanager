<?php

declare(strict_types=1);

namespace App\Application\Sale\Command;

use App\Application\Command\CommandInterface;

/**
 * Verkauft ein einzelnes Exemplar (Inventarnummer) aus dem Bestand. Legt
 * dabei automatisch eine echte Rechnung (Entwurf) mit genau einer Position
 * an (Menge 1, Einzelpreis = $salePrice) — siehe SellInventoryNumberHandler.
 * $customerId ist bewusst optional (Barverkauf ohne hinterlegten Kunden).
 */
final class SellInventoryNumber implements CommandInterface
{
    public function __construct(
        public readonly string $inventoryNumberId,
        public readonly ?string $customerId,
        public readonly string $salePrice, // Rohwert aus dem Formular (z. B. "120,00"), wird im Handler validiert/normalisiert
        public readonly ?string $shippingAmount, // Rohwert, optional
        public readonly ?string $note,
    ) {
    }
}
