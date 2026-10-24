<?php

declare(strict_types=1);

namespace App\Application\Messaging\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Messaging\MessageTemplateRepositoryInterface;

final class ResetMessageTemplateHandler implements CommandHandlerInterface
{
    public function __construct(private readonly MessageTemplateRepositoryInterface $templates)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof ResetMessageTemplate);

        $this->templates->resetToDefault($command->channel, $command->key);

        return null;
    }
}
