<?php

declare(strict_types=1);

namespace App\Application\DigitalStagebox;

use App\Domain\DigitalStagebox\ArticleDigitalStagebox;
use App\Domain\DigitalStagebox\DigitalStageboxRepositoryInterface;

/**
 * Prüft die Angaben einer digitalen Stagebox (Migration 152): ein digitales
 * Protokoll aus Einstellungen › Digitale Protokolle sowie die Anzahl der
 * Eingänge (XLR, Kombi) und Ausgänge (analog, AES/EBU) als ganze Zahlen
 * von 0 bis 999. Leere Felder zählen als 0; Kombinationen sind erlaubt,
 * insgesamt muss die Stagebox aber mindestens einen Ein- oder Ausgang haben.
 */
final class DigitalStageboxBuilder
{
    public function __construct(private readonly DigitalStageboxRepositoryInterface $stageboxes)
    {
    }

    /** @return array{0: array<string, string>, 1: ?ArticleDigitalStagebox} */
    public function build(string $articleId, DigitalStageboxInput $input): array
    {
        $errors = [];
        $protocol = ($input->protocolId ?? '') !== '' ? $this->stageboxes->findProtocolById((string) $input->protocolId) : null;
        if ($protocol === null) {
            $errors['stagebox_protocol_id'] = 'Bitte ein digitales Protokoll wählen (siehe Einstellungen › Digitale Protokolle).';
        }

        $counts = [];
        $fields = [
            'stagebox_inputs_xlr' => [$input->inputsXlr, 'XLR-Eingänge'],
            'stagebox_inputs_combo' => [$input->inputsCombo, 'Kombi-Eingänge'],
            'stagebox_outputs_analog' => [$input->outputsAnalog, 'analogen Ausgänge'],
            'stagebox_outputs_aes' => [$input->outputsAes, 'AES/EBU-Ausgänge'],
        ];
        foreach ($fields as $field => [$raw, $label]) {
            $count = self::count($raw);
            if ($count === null) {
                $errors[$field] = 'Bitte die Anzahl der ' . $label . ' als ganze Zahl von 0 bis ' . ArticleDigitalStagebox::MAX_PER_TYPE . ' angeben.';
            }
            $counts[$field] = $count ?? 0;
        }

        if (!array_intersect_key($errors, $fields) && array_sum($counts) === 0) {
            $errors['stagebox_inputs_xlr'] = 'Eine digitale Stagebox braucht mindestens einen Ein- oder Ausgang.';
        }

        if ($errors !== []) {
            return [$errors, null];
        }

        return [[], new ArticleDigitalStagebox(
            $articleId,
            $protocol->id,
            $counts['stagebox_inputs_xlr'],
            $counts['stagebox_inputs_combo'],
            $counts['stagebox_outputs_analog'],
            $counts['stagebox_outputs_aes'],
        )];
    }

    /** Leer = 0; sonst ganze Zahl 0–999, ungültig = null. */
    private static function count(?string $raw): ?int
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return 0;
        }
        if (preg_match('/^\d{1,3}$/', $raw) !== 1) {
            return null;
        }
        $value = (int) $raw;

        return $value <= ArticleDigitalStagebox::MAX_PER_TYPE ? $value : null;
    }
}
