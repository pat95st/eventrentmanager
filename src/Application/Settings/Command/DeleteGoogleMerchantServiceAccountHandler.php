<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Settings\GoogleMerchantSettingsRepositoryInterface;
use App\Infrastructure\FileStorage\LocalFileStorage;

final class DeleteGoogleMerchantServiceAccountHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly GoogleMerchantSettingsRepositoryInterface $repository,
        private readonly LocalFileStorage $fileStorage,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteGoogleMerchantServiceAccount);

        $current = $this->repository->get();

        if ($current->serviceAccountJsonFilename !== null) {
            $this->fileStorage->delete('integrations/google-merchant', $current->serviceAccountJsonFilename);
            $current->serviceAccountJsonFilename = null;
            $this->repository->save($current);
        }

        return null;
    }
}
