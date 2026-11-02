<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\DocumentIntroTextRepositoryInterface;

final class UpdateDocumentIntroTextsHandler implements CommandHandlerInterface
{
    private const MAX_LENGTH = 3000;

    public function __construct(private readonly DocumentIntroTextRepositoryInterface $texts)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateDocumentIntroTexts);

        $normalized = [];
        foreach (DocumentIntroTextRepositoryInterface::TYPES as $type) {
            $text = str_replace("\r\n", "\n", trim((string) ($command->texts[$type] ?? '')));
            if (mb_strlen($text) > self::MAX_LENGTH) {
                throw new ValidationException([$type => 'Der Starttext darf höchstens ' . self::MAX_LENGTH . ' Zeichen lang sein.']);
            }
            $normalized[$type] = $text !== '' ? $text : null;
        }
        foreach ($normalized as $type => $text) {
            $this->texts->save($type, $text);
        }

        return null;
    }
}
