<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\WhatsappBusinessSettingsRepositoryInterface;

final class UpdateWhatsappBusinessSettingsHandler implements CommandHandlerInterface
{
    public function __construct(private readonly WhatsappBusinessSettingsRepositoryInterface $repository)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateWhatsappBusinessSettings);

        $currency = strtoupper(trim((string) $command->currency));
        if ($currency === '' || strlen($currency) !== 3) {
            throw new ValidationException(['currency' => 'Bitte einen dreistelligen Währungscode angeben (z. B. EUR).']);
        }

        $current = $this->repository->get();
        $current->accessToken = $this->nullIfEmpty($command->accessToken);
        $current->catalogId = $this->nullIfEmpty($command->catalogId);
        $current->currency = $currency;

        $this->repository->save($current);

        return null;
    }

    private function nullIfEmpty(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
