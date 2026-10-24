<?php

declare(strict_types=1);

namespace App\Application\Mail\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Mail\EmailTemplate;
use App\Domain\Mail\EmailTemplateRepositoryInterface;

final class SaveEmailTemplateHandler implements CommandHandlerInterface
{
    public function __construct(private readonly EmailTemplateRepositoryInterface $templates)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof SaveEmailTemplate);

        $subject = trim((string) $command->subject);
        $bodyHtml = trim((string) $command->bodyHtml);

        $errors = [];
        if ($subject === '') {
            $errors['subject'] = 'Bitte einen Betreff angeben.';
        }
        if ($bodyHtml === '') {
            $errors['bodyHtml'] = 'Bitte einen Inhalt angeben.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $this->templates->save(new EmailTemplate($command->key, $subject, $bodyHtml));

        return null;
    }
}
