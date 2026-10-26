<?php

declare(strict_types=1);

namespace App\Application\ClosingTextTemplate\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\ClosingTextTemplate\ClosingTextTemplateFolderRepositoryInterface;
use App\Domain\ClosingTextTemplate\ClosingTextTemplateRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\RichText\HtmlSanitizer;
use DateTimeImmutable;

final class UpdateClosingTextTemplateHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly ClosingTextTemplateRepositoryInterface $templates,
        private readonly ClosingTextTemplateFolderRepositoryInterface $folders,
        private readonly HtmlSanitizer $htmlSanitizer,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateClosingTextTemplate);

        $template = $this->templates->findById($command->id);
        if ($template === null) {
            throw new NotFoundException("Textvorlage \"{$command->id}\" wurde nicht gefunden.");
        }

        $name = trim($command->name);
        if ($name === '') {
            throw new ValidationException(['name' => 'Bitte einen Namen angeben.']);
        }

        if ($command->folderId !== null && $this->folders->findById($command->folderId) === null) {
            throw new ValidationException(['folderId' => 'Der gewählte Ordner existiert nicht.']);
        }

        $template->name = $name;
        $template->folderId = $command->folderId;
        $template->content = $this->htmlSanitizer->sanitize($command->content);
        $template->updatedAt = new DateTimeImmutable();

        $this->templates->save($template);

        return null;
    }
}
