<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Settings\AiAdImageSettings;
use App\Domain\Settings\AiAdImageSettingsRepositoryInterface;

final class UpdateAiAdImageSettingsHandler implements CommandHandlerInterface
{
    public function __construct(private readonly AiAdImageSettingsRepositoryInterface $settingsRepository)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateAiAdImageSettings);

        $current = $this->settingsRepository->get();

        $resolvedApiKey = $this->nullIfEmpty($command->openaiApiKey) ?? $current->openaiApiKey;

        $this->settingsRepository->save(new AiAdImageSettings(
            openaiApiKey: $resolvedApiKey,
        ));

        return null;
    }

    private function nullIfEmpty(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
    }
}
