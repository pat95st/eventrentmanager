<?php

declare(strict_types=1);

namespace App\Domain\InvoiceCorrection;

/** Entwurf (bearbeit-/löschbar) → Ausgestellt (Nummer vergeben, unveränderlich). */
enum InvoiceCorrectionStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Entwurf',
            self::Issued => 'Ausgestellt',
        };
    }
}
