<?php

declare(strict_types=1);

namespace App\Application\Customer\Command;

use App\Application\Command\CommandInterface;

final class DeleteCustomer implements CommandInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
