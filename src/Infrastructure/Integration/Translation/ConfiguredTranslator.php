<?php

declare(strict_types=1);

namespace App\Infrastructure\Integration\Translation;

use App\Application\EventAudio\Translation\TextTranslatorInterface;
use App\Application\EventAudio\Translation\TranslationException;
use App\Domain\EventAudio\AudioSettingsRepositoryInterface;
use App\Domain\Settings\AiAdImageSettingsRepositoryInterface;

/** Wählt den in Einstellungen › Veranstaltungs-Audio konfigurierten Übersetzer. */
final class ConfiguredTranslator implements TextTranslatorInterface
{
    public function __construct(
        private readonly AudioSettingsRepositoryInterface $settings,
        private readonly AiAdImageSettingsRepositoryInterface $aiSettings,
        private readonly DeeplTranslator $deepl = new DeeplTranslator(),
        private readonly OpenAiTranslator $openai = new OpenAiTranslator(),
    ) {
    }

    public function isAvailable(): bool
    {
        $s = $this->settings->get();

        return match ($s->translatorProvider) {
            'deepl' => $s->deeplApiKey !== null && trim($s->deeplApiKey) !== '',
            'openai' => $this->aiSettings->get()->isConfigured(),
            default => false,
        };
    }

    public function providerLabel(): string
    {
        return match ($this->settings->get()->translatorProvider) {
            'deepl' => 'DeepL',
            'openai' => 'OpenAI',
            default => '',
        };
    }

    public function translate(string $text, string $sourceLanguage, string $targetLanguage): string
    {
        $text = trim($text);
        if ($text === '') {
            throw new TranslationException('Kein Text zum Übersetzen.');
        }
        if (mb_strlen($text) > 1000) {
            throw new TranslationException('Der Text ist zu lang (max. 1000 Zeichen).');
        }
        if (strtolower(substr($sourceLanguage, 0, 2)) === strtolower(substr($targetLanguage, 0, 2)) && $sourceLanguage === $targetLanguage) {
            return $text;
        }
        $s = $this->settings->get();

        return match ($s->translatorProvider) {
            'deepl' => $this->deepl->translate((string) $s->deeplApiKey, $text, $sourceLanguage, $targetLanguage),
            'openai' => $this->openai->translate((string) $this->aiSettings->get()->openaiApiKey, $s->openaiTranslationModel, $text, $sourceLanguage, $targetLanguage),
            default => throw new TranslationException('Es ist kein Übersetzer eingerichtet (Einstellungen › Veranstaltungs-Audio).'),
        };
    }
}
