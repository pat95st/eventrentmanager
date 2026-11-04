<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\PdfFontRepositoryInterface;
use App\Domain\Settings\UiFontRole;

final class UpdateUiFontAssignmentsHandler implements CommandHandlerInterface
{
    public function __construct(private readonly PdfFontRepositoryInterface $fonts)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateUiFontAssignments);
        $resolved = [];
        foreach (UiFontRole::cases() as $role) {
            $fontId = trim((string) ($command->assignments[$role->value] ?? ''));
            if ($fontId !== '' && $this->fonts->findById($fontId) === null) {
                throw new ValidationException([$role->value => 'Die gewählte Schrift existiert nicht mehr.']);
            }
            $resolved[$role->value] = $fontId !== '' ? $fontId : null;
        }
        foreach ($resolved as $role => $fontId) {
            $this->fonts->assign($role, $fontId);
        }

        return null;
    }
}
