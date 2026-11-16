<?php

declare(strict_types=1);

namespace App\Domain\MinuteProgram;

/**
 * Helpers for the per-trade notes of minute program (template) items and the
 * trade selection of programs/templates (migration 172).
 *
 * Notes are stored as a JSON object {"<trade id>": "<note>"}, the trade
 * selection as a comma-separated list of trade IDs (NULL = automatic).
 */
final class MinuteProgramTradeNotes
{
    /**
     * Trims all notes and drops empty ones and invalid keys.
     *
     * @param array<mixed> $notes
     *
     * @return array<string, string>
     */
    public static function normalize(array $notes): array
    {
        $result = [];
        foreach ($notes as $tradeId => $note) {
            if (!is_string($tradeId) && !is_int($tradeId)) {
                continue;
            }
            $tradeId = trim((string) $tradeId);
            if ($tradeId === '' || !is_scalar($note)) {
                continue;
            }
            $note = trim((string) $note);
            if ($note !== '') {
                $result[$tradeId] = $note;
            }
        }

        return $result;
    }

    /**
     * Applies submitted notes to existing ones: a submitted trade overwrites
     * (or, when empty, removes) its note, notes of trades that were not
     * submitted (e.g. a trade no longer selected in the program) are kept.
     *
     * @param array<string, string> $existing
     * @param array<mixed>          $submitted
     *
     * @return array<string, string>
     */
    public static function merge(array $existing, array $submitted): array
    {
        $result = $existing;
        foreach ($submitted as $tradeId => $note) {
            if (!is_string($tradeId) && !is_int($tradeId)) {
                continue;
            }
            unset($result[(string) $tradeId]);
        }

        return $result + self::normalize($submitted);
    }

    /** @param array<string, string> $notes */
    public static function encode(array $notes): ?string
    {
        $notes = self::normalize($notes);

        return $notes === [] ? null : json_encode($notes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /** @return array<string, string> */
    public static function decode(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }
        $data = json_decode($json, true);

        return is_array($data) ? self::normalize($data) : [];
    }

    /** @param list<string>|null $ids */
    public static function encodeIds(?array $ids): ?string
    {
        return $ids === null ? null : implode(',', self::normalizeIds($ids));
    }

    /** @return list<string>|null */
    public static function decodeIds(?string $value): ?array
    {
        return $value === null ? null : self::normalizeIds(explode(',', $value));
    }

    /**
     * @param array<mixed> $ids
     *
     * @return list<string>
     */
    public static function normalizeIds(array $ids): array
    {
        $result = [];
        foreach ($ids as $id) {
            $id = is_scalar($id) ? trim((string) $id) : '';
            if ($id !== '' && !in_array($id, $result, true)) {
                $result[] = $id;
            }
        }

        return $result;
    }
}
