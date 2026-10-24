<?php

declare(strict_types=1);

namespace App\Application\Rma\Command;

use App\Application\Command\CommandInterface;

final class DeleteRmaPackagePhoto implements CommandInterface
{
    public function __construct(public readonly string $photoId)
    {
    }
}
