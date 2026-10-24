<?php

declare(strict_types=1);

namespace App\Application\Mail\Command;

use App\Application\Command\CommandInterface;
use App\Domain\Mail\EmailTemplateKey;

final class ResetEmailTemplate implements CommandInterface
{
    public function __construct(public readonly EmailTemplateKey $key)
    {
    }
}
