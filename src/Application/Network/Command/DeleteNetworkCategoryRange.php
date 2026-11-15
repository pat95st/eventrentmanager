<?php

declare(strict_types=1);

namespace App\Application\Network\Command;

use App\Application\Command\CommandInterface;

final class DeleteNetworkCategoryRange implements CommandInterface
{
    public function __construct(public readonly string $categoryId)
    {
    }
}
