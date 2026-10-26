<?php

declare(strict_types=1);

namespace App\Application\ClosingTextTemplate\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\ClosingTextTemplate\ClosingTextTemplate;
use App\Domain\ClosingTextTemplate\ClosingTextTemplateFolderRepositoryInterface;
use App\Domain\ClosingTextTemplate\ClosingTextTemplateRepositoryInterface;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\RichText\HtmlSanitizer;
use App\Shared\Uuid;
use DateTimeImmutable;

final class CreateClosingTextTemplateHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly ClosingTextTemplateRepositoryInterface $templates,
        private readonly ClosingTextTemplateFolderRepositoryInterface $folders,
        private readonly HtmlSanitizer $htmlSanitizer,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreateClosingTextTemplate);

        $name = trim($command->name);
        if ($name === '') {
            throw new ValidationException(['name' => 'Bitte einen Namen angeben.']);
        }

        if ($command->folderId !== null && $this->folders->findById($command->folderId) === null) {
            throw new ValidationException(['folderId' => 'Der gewählte Ordner existiert nicht.']);
        }

        $now = new DateTimeImmutable();
        $template = new ClosingTextTemplate(
            id: Uuid::v4(),
            folderId: $command->folderId,
            name: $name,
            content: $this->htmlSanitizer->sanitize($command->content),
            createdAt: $now,
            updatedAt: $now,
        );

        $this->templates->save($template);

        return $template->id;
    }
}
