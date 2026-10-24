<?php

declare(strict_types=1);

namespace App\Application\Mail\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Mail\EmailTemplateRepositoryInterface;

final class ResetEmailTemplateHandler implements CommandHandlerInterface
{
    public function __construct(private readonly EmailTemplateRepositoryInterface $templates)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof ResetEmailTemplate);

        $this->templates->resetToDefault($command->key);

        return null;
    }
}
