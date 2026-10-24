<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Application\Command\CommandInterface;

/** Deaktiviert den zweiten Faktor — von der/dem Benutzer:in selbst oder als Admin-Recovery ("2FA zurücksetzen"). */
final class DisableTwoFactor implements CommandInterface
{
    public function __construct(public readonly string $userId)
    {
    }
}
