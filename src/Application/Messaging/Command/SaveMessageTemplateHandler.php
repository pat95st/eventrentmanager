<?php

declare(strict_types=1);

namespace App\Application\Messaging\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Messaging\MessageTemplate;
use App\Domain\Messaging\MessageTemplateRepositoryInterface;
use App\Domain\Messaging\MessagingChannel;

final class SaveMessageTemplateHandler implements CommandHandlerInterface
{
    public function __construct(private readonly MessageTemplateRepositoryInterface $templates)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof SaveMessageTemplate);

        if (!$command->key->appliesToChannel($command->channel)) {
            throw new ValidationException(['key' => 'Dieser Vorlagenschlüssel gilt nicht für ' . $command->channel->label() . '.']);
        }

        $errors = [];

        if ($command->channel === MessagingChannel::WhatsApp) {
            $metaTemplateName = trim((string) $command->metaTemplateName);
            if ($metaTemplateName === '') {
                $errors['metaTemplateName'] = 'Bitte den bei Meta genehmigten Vorlagennamen angeben.';
            }
            $metaTemplateLanguage = trim((string) $command->metaTemplateLanguage);
            if ($metaTemplateLanguage === '') {
                $metaTemplateLanguage = 'de';
            }

            if ($errors !== []) {
                throw new ValidationException($errors);
            }

            $this->templates->save(new MessageTemplate(
                $command->channel,
                $command->key,
                bodyText: null,
                metaTemplateName: $metaTemplateName,
                metaTemplateLanguage: $metaTemplateLanguage,
            ));

            return null;
        }

        $bodyText = trim((string) $command->bodyText);
        if ($bodyText === '') {
            $errors['bodyText'] = 'Bitte einen Nachrichtentext angeben.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $this->templates->save(new MessageTemplate($command->channel, $command->key, bodyText: $bodyText));

        return null;
    }
}
