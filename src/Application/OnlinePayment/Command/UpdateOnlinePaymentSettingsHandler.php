<?php

declare(strict_types=1);

namespace App\Application\OnlinePayment\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\OnlinePayment\OnlinePaymentSettingsRepositoryInterface;

final class UpdateOnlinePaymentSettingsHandler implements CommandHandlerInterface
{
    public function __construct(private readonly OnlinePaymentSettingsRepositoryInterface $repository)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateOnlinePaymentSettings);

        $settings = $this->repository->get();
        $errors = [];

        $currency = strtoupper(trim((string) $command->currency));
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            $errors['currency'] = 'Bitte einen dreistelligen Währungscode angeben (z. B. EUR).';
        }

        $settings->paypalEnabled = $command->paypalEnabled;
        $settings->paypalSandbox = $command->paypalSandbox;
        $settings->paypalClientId = $this->nullIfEmpty($command->paypalClientId);
        $paypalSecret = $this->nullIfEmpty($command->paypalClientSecret);
        if ($paypalSecret !== null) {
            $settings->paypalClientSecret = $paypalSecret;
        }

        $settings->sumupEnabled = $command->sumupEnabled;
        $sumupKey = $this->nullIfEmpty($command->sumupApiKey);
        if ($sumupKey !== null) {
            $settings->sumupApiKey = $sumupKey;
        }
        $settings->sumupMerchantCode = $this->nullIfEmpty($command->sumupMerchantCode);

        if ($settings->paypalEnabled && $settings->paypalClientId === null) {
            $errors['paypal_client_id'] = 'Bitte die Client-ID der PayPal-App angeben.';
        }
        if ($settings->paypalEnabled && $settings->paypalClientSecret === null) {
            $errors['paypal_client_secret'] = 'Bitte das Secret der PayPal-App angeben.';
        }
        if ($settings->sumupEnabled && $settings->sumupApiKey === null) {
            $errors['sumup_api_key'] = 'Bitte den SumUp-API-Schlüssel angeben.';
        }
        if ($settings->sumupEnabled && $settings->sumupMerchantCode === null) {
            $errors['sumup_merchant_code'] = 'Bitte den SumUp-Händlercode angeben.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $settings->currency = $currency;
        $this->repository->save($settings);

        return null;
    }

    private function nullIfEmpty(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
