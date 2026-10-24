<?php

declare(strict_types=1);

namespace App\Application\Rental\Command;

use App\Application\Command\CommandInterface;

/**
 * Bestätigt, dass bei diesem Kunden ein Ausweisdokument geprüft wurde
 * (einmalig je Kunde, siehe Customer::$idCheckedAt und
 * „Vermietbedingungen-Unterschrift & Ausweisprüfung“ in
 * architektur-und-setup.md).
 */
final class ConfirmCustomerIdChecked implements CommandInterface
{
    public function __construct(public readonly string $customerId)
    {
    }
}
