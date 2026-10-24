<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Settings\CalDavSigningSettingsRepositoryInterface;
use App\Infrastructure\FileStorage\LocalFileStorage;
use DateTimeImmutable;

final class DeleteCalDavSigningCertificateHandler implements CommandHandlerInterface
{
    private const SUBDIRECTORY = 'caldav-signing';

    public function __construct(
        private readonly CalDavSigningSettingsRepositoryInterface $repository,
        private readonly LocalFileStorage $fileStorage,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteCalDavSigningCertificate);

        $settings = $this->repository->get();

        if ($settings->certificateFilename !== null) {
            $this->fileStorage->delete(self::SUBDIRECTORY, $settings->certificateFilename);
        }
        if ($settings->privateKeyFilename !== null) {
            $this->fileStorage->delete(self::SUBDIRECTORY, $settings->privateKeyFilename);
        }

        $settings->certificateFilename = null;
        $settings->privateKeyFilename = null;
        $settings->isSelfSigned = false;
        $settings->updatedAt = new DateTimeImmutable();

        $this->repository->save($settings);

        return null;
    }
}
