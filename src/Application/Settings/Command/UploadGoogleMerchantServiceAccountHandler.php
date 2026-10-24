<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\GoogleMerchantSettingsRepositoryInterface;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Shared\Uuid;

final class UploadGoogleMerchantServiceAccountHandler implements CommandHandlerInterface
{
    private const MAX_SIZE_BYTES = 1 * 1024 * 1024; // 1 MB reicht für eine Service-Account-JSON-Datei bei Weitem

    public function __construct(
        private readonly GoogleMerchantSettingsRepositoryInterface $repository,
        private readonly LocalFileStorage $fileStorage,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UploadGoogleMerchantServiceAccount);

        $file = $command->uploadedFile;

        if ($file['size'] > self::MAX_SIZE_BYTES) {
            throw new ValidationException(['service_account_json' => 'Die Datei ist zu groß (maximal 1 MB).']);
        }

        $content = file_get_contents($file['tmp_name']);
        $decoded = $content !== false ? json_decode($content, true) : null;

        if (!is_array($decoded) || ($decoded['type'] ?? null) !== 'service_account' || !isset($decoded['client_email'], $decoded['private_key'])) {
            throw new ValidationException(['service_account_json' => 'Das ist keine gültige Google-Service-Account-JSON-Datei (aus der Google Cloud Console > IAM & Verwaltung > Dienstkonten > Schlüssel erstellen).']);
        }

        $current = $this->repository->get();

        if ($current->serviceAccountJsonFilename !== null) {
            $this->fileStorage->delete('integrations/google-merchant', $current->serviceAccountJsonFilename);
        }

        $stored = $this->fileStorage->storeUploadedFile($file, 'integrations/google-merchant', Uuid::v4());

        $current->serviceAccountJsonFilename = $stored['storedFilename'];
        $this->repository->save($current);

        return null;
    }
}
