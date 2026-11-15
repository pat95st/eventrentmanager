<?php

declare(strict_types=1);

namespace App\Application\AnalogMulticore;

use App\Domain\AnalogMulticore\ArticleAnalogMulticore;

/**
 * Validates the details of an analog multicore (migration 156): sends and
 * returns as whole numbers from 0 to 999 (empty = 0), at least one channel
 * in total, plus the "on drum" and "Harting connector" flags.
 */
final class AnalogMulticoreBuilder
{
    /** @return array{0: array<string, string>, 1: ?ArticleAnalogMulticore} */
    public function build(string $articleId, AnalogMulticoreInput $input): array
    {
        $errors = [];
        $counts = [];
        $fields = [
            'multicore_sends' => [$input->sends, 'Sends'],
            'multicore_returns' => [$input->returns, 'Returns'],
        ];
        foreach ($fields as $field => [$raw, $label]) {
            $count = self::count($raw);
            if ($count === null) {
                $errors[$field] = 'Bitte die Anzahl der ' . $label . ' als ganze Zahl von 0 bis ' . ArticleAnalogMulticore::MAX_CHANNELS . ' angeben.';
            }
            $counts[$field] = $count ?? 0;
        }

        if ($errors === [] && array_sum($counts) === 0) {
            $errors['multicore_sends'] = 'Ein Multicore braucht mindestens einen Send oder Return.';
        }

        if ($errors !== []) {
            return [$errors, null];
        }

        return [[], new ArticleAnalogMulticore(
            $articleId,
            $counts['multicore_sends'],
            $counts['multicore_returns'],
            $input->onDrum,
            $input->hasHarting,
        )];
    }

    /** Empty = 0; otherwise a whole number 0–999, invalid = null. */
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

        return $value <= ArticleAnalogMulticore::MAX_CHANNELS ? $value : null;
    }
}
