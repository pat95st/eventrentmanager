<?php

declare(strict_types=1);

namespace App\Infrastructure\Integration\Insurance;

use App\Application\EventAudio\Translation\TranslationException;
use App\Application\Insurance\Estimation\CurrentValueEstimate;
use App\Application\Insurance\Estimation\CurrentValueEstimationException;
use App\Application\Insurance\Estimation\CurrentValueEstimationRequest;
use App\Application\Insurance\Estimation\CurrentValueEstimatorInterface;
use App\Domain\Settings\AiAdImageSettingsRepositoryInterface;
use App\Infrastructure\Integration\Translation\HttpJsonClient;

/**
 * Schätzt den Zeitwert eines Geräts über die OpenAI Chat-Completions-API
 * (JSON-Antwort). Nutzt denselben API-Schlüssel wie die KI-Werbebilder und
 * die OpenAI-Übersetzung (Einstellungen › KI-Werbebilder).
 */
final class OpenAiCurrentValueEstimator implements CurrentValueEstimatorInterface
{
    private const URL = 'https://api.openai.com/v1/chat/completions';
    public const DEFAULT_MODEL = 'gpt-4o-mini';

    public function __construct(
        private readonly AiAdImageSettingsRepositoryInterface $settings,
        private readonly HttpJsonClient $http = new HttpJsonClient(),
        private readonly string $model = self::DEFAULT_MODEL,
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->settings->get()->isConfigured();
    }

    public function estimate(CurrentValueEstimationRequest $request): CurrentValueEstimate
    {
        $apiKey = trim((string) $this->settings->get()->openaiApiKey);
        if ($apiKey === '') {
            throw new CurrentValueEstimationException('Es ist kein OpenAI-API-Schlüssel hinterlegt.');
        }

        try {
            [$status, $json] = $this->http->postJson(self::URL, [
                'model' => $this->model,
                'temperature' => 0.1,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => self::systemPrompt()],
                    ['role' => 'user', 'content' => self::userPrompt($request)],
                ],
            ], ['Authorization: Bearer ' . $apiKey], 30);
        } catch (TranslationException $e) {
            throw new CurrentValueEstimationException('OpenAI ist nicht erreichbar: ' . $e->getMessage(), 0, $e);
        }

        if ($status === 401) {
            throw new CurrentValueEstimationException('OpenAI lehnt den API-Schlüssel ab.');
        }
        if ($status === 429) {
            throw new CurrentValueEstimationException('OpenAI: Limit erreicht – bitte kurz warten.');
        }
        $content = $json['choices'][0]['message']['content'] ?? null;
        if ($status !== 200 || !is_string($content)) {
            $msg = is_array($json['error'] ?? null) ? (string) ($json['error']['message'] ?? '') : '';
            throw new CurrentValueEstimationException('OpenAI konnte keinen Zeitwert schätzen (HTTP ' . $status . ($msg !== '' ? ': ' . mb_substr($msg, 0, 160) : '') . ').');
        }

        return self::parseAnswer($content);
    }

    /** @internal öffentlich für Tests */
    public static function parseAnswer(string $content): CurrentValueEstimate
    {
        $data = json_decode(trim($content), true);
        if (!is_array($data)) {
            throw new CurrentValueEstimationException('Die KI-Antwort war kein gültiges JSON.');
        }
        $value = $data['current_value_eur'] ?? null;
        if (is_string($value)) {
            $value = str_replace(',', '.', preg_replace('/[^0-9,.]/', '', $value) ?? '');
        }
        if (!is_numeric($value) || (float) $value < 0) {
            throw new CurrentValueEstimationException('Die KI konnte für dieses Gerät keinen Zeitwert schätzen.');
        }
        $reasoning = isset($data['reasoning']) && is_string($data['reasoning']) && trim($data['reasoning']) !== ''
            ? trim($data['reasoning'])
            : null;

        return new CurrentValueEstimate(number_format(round((float) $value, 2), 2, '.', ''), $reasoning);
    }

    private static function systemPrompt(): string
    {
        return 'Du bist Sachverständiger für gebrauchte Veranstaltungstechnik (Licht, Ton, Video, Bühne, Strom, Kabel) in Deutschland. '
            . 'Schätze den heutigen Zeitwert (Wiederbeschaffungswert eines gleichwertigen gebrauchten Geräts, wie er für eine '
            . 'Geräteversicherung angesetzt wird) eines Mietgeräts in Euro. Berücksichtige typischen Gebrauchtmarktpreis, Alter, '
            . 'übliche Abnutzung im Vermietbetrieb und – falls angegeben – den Anschaffungspreis. Ist das Anschaffungsjahr unbekannt, '
            . 'gehe von einem durchschnittlich gebrauchten Gerät aus. '
            . 'Antworte ausschließlich als JSON-Objekt: {"current_value_eur": <Zahl>, "reasoning": "<ein kurzer Satz auf Deutsch>"}. '
            . 'Kannst du das Gerät überhaupt nicht einordnen, setze "current_value_eur" auf null.';
    }

    /** @internal öffentlich für Tests */
    public static function userPrompt(CurrentValueEstimationRequest $request): string
    {
        $lines = ['Heutiges Datum: ' . $request->today->format('Y-m-d')];
        if ($request->category !== null && $request->category !== '') {
            $lines[] = 'Kategorie: ' . $request->category;
        }
        if ($request->manufacturer !== null && $request->manufacturer !== '') {
            $lines[] = 'Hersteller: ' . $request->manufacturer;
        }
        $lines[] = 'Modell/Bezeichnung: ' . $request->model;
        if ($request->description !== null) {
            $lines[] = 'Beschreibung: ' . $request->description;
        }
        $lines[] = 'Anschaffung: ' . ($request->acquisitionDate !== null ? $request->acquisitionDate->format('Y-m') : 'unbekannt');
        $lines[] = 'Anschaffungspreis (netto): ' . ($request->purchasePrice !== null ? number_format((float) $request->purchasePrice, 2, '.', '') . ' EUR' : 'unbekannt');

        return implode("\n", $lines);
    }
}
