<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Settings\AiAdImageSettings;
use App\Domain\Settings\AiAdImageSettingsRepositoryInterface;
use App\Domain\Exception\ValidationException;

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
            imageModel: $this->resolveImageModel($command->imageModel),
        ));

        return null;
    }

    private function resolveImageModel(?string $value): ?string
    {
        $model = $this->nullIfEmpty($value);
        if ($model === null) {
            return null;
        }
        if (mb_strlen($model) > 100 || preg_match('/^[A-Za-z0-9._:-]+$/', $model) !== 1) {
            throw new ValidationException(['image_model' => 'Bitte einen gültigen Modellnamen angeben (z. B. ' . AiAdImageSettings::DEFAULT_IMAGE_MODEL . ').']);
        }

        return $model;
    }

    private function nullIfEmpty(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
    }
}
