<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandInterface;
use App\Domain\Settings\BackgroundSlot;

final class DeleteCompanyBackgroundImage implements CommandInterface
{
    public function __construct(public readonly BackgroundSlot $slot = BackgroundSlot::App)
    {
    }
}
