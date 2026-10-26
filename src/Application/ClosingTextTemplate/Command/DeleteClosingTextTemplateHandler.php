<?php

declare(strict_types=1);

namespace App\Application\ClosingTextTemplate\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\ClosingTextTemplate\ClosingTextTemplateRepositoryInterface;
use App\Domain\Exception\NotFoundException;

final class DeleteClosingTextTemplateHandler implements CommandHandlerInterface
{
    public function __construct(private readonly ClosingTextTemplateRepositoryInterface $templates)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteClosingTextTemplate);

        if ($this->templates->findById($command->id) === null) {
            throw new NotFoundException("Textvorlage \"{$command->id}\" wurde nicht gefunden.");
        }

        $this->templates->delete($command->id);

        return null;
    }
}
