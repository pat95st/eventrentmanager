<?php

declare(strict_types=1);

namespace App\Application\Offer\Command;

use App\Application\Command\CommandInterface;

final class MarkOfferAccepted implements CommandInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
