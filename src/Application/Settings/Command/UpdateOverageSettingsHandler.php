<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\OverageSettings;
use App\Domain\Settings\OverageSettingsRepositoryInterface;

/** Nur leichte Validierung: flatFeeAmount muss numerisch und >= 0 sein (siehe ValidatesDunningLevelInput::$feeValue). */
final class UpdateOverageSettingsHandler implements CommandHandlerInterface
{
    public function __construct(private readonly OverageSettingsRepositoryInterface $settingsRepository)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateOverageSettings);

        $errors = [];

        $rawAmount = str_replace(',', '.', trim($command->flatFeeAmount));
        $amountValue = filter_var($rawAmount, FILTER_VALIDATE_FLOAT);
        if ($amountValue === false || $amountValue < 0) {
            $errors['flatFeeAmount'] = 'Bitte einen gültigen Betrag (0 oder mehr) angeben.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $this->settingsRepository->save(new OverageSettings(
            flatFeeEnabled: $command->flatFeeEnabled,
            flatFeeAmount: number_format((float) $amountValue, 2, '.', ''),
        ));

        return null;
    }
}
