<?php

declare(strict_types=1);

namespace App\Domain\Purchase;

/**
 * Status einer Bestellung. "Bestellt" (Ordered) ist der einzige Status, in
 * dem Wareneingänge (siehe ReceivePurchaseOrderItemHandler) erfasst werden
 * können. "Abgeschlossen" wird automatisch gesetzt, sobald alle Positionen
 * vollständig eingetroffen sind (siehe PurchaseOrderItem::isFullyReceived()),
 * kann aber ebenso manuell vorzeitig gesetzt werden (z. B. wenn ein Rest
 * nicht mehr erwartet wird). Eine Bestellung im Entwurf kann noch beliebig
 * bearbeitet werden (Positionen hinzufügen/entfernen); danach nicht mehr
 * (siehe UpdatePurchaseOrderHandler).
 */
enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Ordered = 'ordered';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Entwurf',
            self::Ordered => 'Bestellt',
            self::Completed => 'Abgeschlossen',
            self::Cancelled => 'Storniert',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Draft => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
            self::Ordered => 'bg-blue-100 text-blue-700 dark:bg-blue-950/40 dark:text-blue-400',
            self::Completed => 'bg-green-100 text-green-700 dark:bg-green-950/40 dark:text-green-400',
            self::Cancelled => 'bg-red-100 text-red-700 dark:bg-red-950/40 dark:text-red-400',
        };
    }
}
