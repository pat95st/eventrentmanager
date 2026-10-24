<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\GoogleMerchantSettingsRepositoryInterface;

final class UpdateGoogleMerchantSettingsHandler implements CommandHandlerInterface
{
    public function __construct(private readonly GoogleMerchantSettingsRepositoryInterface $repository)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateGoogleMerchantSettings);

        $errors = [];
        $targetCountry = strtoupper(trim((string) $command->targetCountry));
        if ($targetCountry === '' || strlen($targetCountry) !== 2) {
            $errors['targetCountry'] = 'Bitte einen zweistelligen Ländercode angeben (z. B. DE).';
        }
        $contentLanguage = strtolower(trim((string) $command->contentLanguage));
        if ($contentLanguage === '') {
            $errors['contentLanguage'] = 'Bitte einen Sprachcode angeben (z. B. de).';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        // WICHTIG: service_account_json_filename wird hier bewusst NICHT
        // angefasst (siehe Settings-Bug-Muster aus CompanySettings) — die
        // Datei wird ausschließlich über UploadGoogleMerchantServiceAccount/
        // DeleteGoogleMerchantServiceAccount verwaltet.
        $current = $this->repository->get();
        $current->merchantId = $this->nullIfEmpty($command->merchantId);
        $current->targetCountry = $targetCountry;
        $current->contentLanguage = $contentLanguage;
        $current->productLinkTemplate = $this->nullIfEmpty($command->productLinkTemplate);

        $this->repository->save($current);

        return null;
    }

    private function nullIfEmpty(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
