<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Settings;

use App\Application\Settings\Command\UpdateAiAdImageSettings;
use App\Application\Settings\Command\UpdateAiAdImageSettingsHandler;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\AiAdImageSettings;
use App\Domain\Settings\AiAdImageSettingsRepositoryInterface;
use Codeception\Test\Unit;

final class UpdateAiAdImageSettingsHandlerTest extends Unit
{
    private AiAdImageSettingsRepositoryInterface $repository;
    private UpdateAiAdImageSettingsHandler $handler;

    protected function _before(): void
    {
        $this->repository = new class implements AiAdImageSettingsRepositoryInterface {
            public AiAdImageSettings $settings;

            public function __construct()
            {
                $this->settings = new AiAdImageSettings(openaiApiKey: 'sk-existing', imageModel: 'gpt-image-1');
            }

            public function get(): AiAdImageSettings
            {
                return $this->settings;
            }

            public function save(AiAdImageSettings $settings): void
            {
                $this->settings = $settings;
            }
        };
        $this->handler = new UpdateAiAdImageSettingsHandler($this->repository);
    }

    public function testStoresSelectedModelAndKeepsExistingKey(): void
    {
        $this->handler->handle(new UpdateAiAdImageSettings(openaiApiKey: '', imageModel: ' gpt-image-1-mini '));

        $saved = $this->repository->get();
        $this->assertSame('sk-existing', $saved->openaiApiKey);
        $this->assertSame('gpt-image-1-mini', $saved->imageModel);
        $this->assertSame('gpt-image-1-mini', $saved->effectiveImageModel());
    }

    public function testEmptyModelFallsBackToDefault(): void
    {
        $this->handler->handle(new UpdateAiAdImageSettings(openaiApiKey: null, imageModel: ''));

        $saved = $this->repository->get();
        $this->assertNull($saved->imageModel);
        $this->assertSame(AiAdImageSettings::DEFAULT_IMAGE_MODEL, $saved->effectiveImageModel());
    }

    public function testRejectsInvalidModelName(): void
    {
        $this->expectException(ValidationException::class);

        $this->handler->handle(new UpdateAiAdImageSettings(openaiApiKey: null, imageModel: 'gpt image; drop'));
    }
}
