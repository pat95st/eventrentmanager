<?php

declare(strict_types=1);

namespace App\Application\Cable;

use App\Domain\Cable\ArticleCable;
use App\Domain\Cable\CableMulticore;
use App\Domain\Cable\CableRepositoryInterface;
use App\Domain\Cable\CableType;
use App\Domain\Cable\ConnectorType;

/**
 * Prüft die Leitungsangaben eines Artikels (Migration 125): Leitungstyp,
 * Länge (0,1–1000 m) und zwei Steckverbinder, die für diesen Leitungstyp
 * erlaubt sind (Einstellungen › Leitungen).
 * For analog multicore types (migration 157) also sends and returns
 * (0–999 each, at least one channel), drum and Harting flags.
 */
final class CableBuilder
{
    public function __construct(private readonly CableRepositoryInterface $cables)
    {
    }

    /** @return array{0: array<string, string>, 1: ?ArticleCable, 2: ?CableType} */
    public function build(string $articleId, CableInput $input): array
    {
        $errors = [];
        $type = ($input->cableTypeId ?? '') !== '' ? $this->cables->findTypeById((string) $input->cableTypeId) : null;
        if ($type === null) {
            $errors['cable_type_id'] = 'Bitte einen Leitungstyp wählen.';
        }

        $raw = str_replace(',', '.', trim((string) $input->length));
        if ($raw === '' || !is_numeric($raw) || (float) $raw < 0.1 || (float) $raw > 1000) {
            $errors['cable_length'] = 'Bitte die Länge in Metern angeben (0,1 bis 1000).';
        }

        foreach (['cable_connector_a' => $input->connectorAId, 'cable_connector_b' => $input->connectorBId] as $field => $connectorId) {
            $connector = ($connectorId ?? '') !== '' ? $this->cables->findConnectorById((string) $connectorId) : null;
            if ($connector === null) {
                $errors[$field] = 'Bitte einen Steckverbinder wählen.';
            } elseif ($type !== null && !$type->allowsConnector($connector->id)) {
                $errors[$field] = '„' . $connector->name . '“ passt nicht zum Leitungstyp ' . $type->name . ' (siehe Einstellungen › Leitungen).';
            }
        }

        $multicore = null;
        if ($type !== null && $type->isAnalogMulticore) {
            [$multicoreErrors, $multicore] = self::buildMulticore($input);
            $errors = array_merge($errors, $multicoreErrors);
        }

        if ($errors !== []) {
            return [$errors, null, $type];
        }

        return [[], new ArticleCable($articleId, $type->id, number_format((float) $raw, 2, '.', ''), (string) $input->connectorAId, (string) $input->connectorBId, $multicore), $type];
    }

    /** @return array{0: array<string, string>, 1: ?CableMulticore} */
    private static function buildMulticore(CableInput $input): array
    {
        $errors = [];
        $counts = [];
        foreach (['multicore_sends' => [$input->multicoreSends, 'Sends'], 'multicore_returns' => [$input->multicoreReturns, 'Returns']] as $field => [$raw, $label]) {
            $count = self::count($raw);
            if ($count === null) {
                $errors[$field] = 'Bitte die Anzahl der ' . $label . ' als ganze Zahl von 0 bis ' . CableMulticore::MAX_CHANNELS . ' angeben.';
            }
            $counts[$field] = $count ?? 0;
        }
        if ($errors === [] && array_sum($counts) === 0) {
            $errors['multicore_sends'] = 'Ein Multicore braucht mindestens einen Send oder Return.';
        }
        if ($errors !== []) {
            return [$errors, null];
        }

        return [[], new CableMulticore($counts['multicore_sends'], $counts['multicore_returns'], $input->multicoreOnDrum, $input->multicoreHasHarting)];
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

        return (int) $raw <= CableMulticore::MAX_CHANNELS ? (int) $raw : null;
    }

    /**
     * Vorschlag für den Artikelnamen, z. B. "H07RN-F 5G6 10 m CEE 32 A Stecker rot / CEE 32 A Kupplung rot";
     * multicores include sends/returns, e.g. "Analoges Multicore 24/8 30 m Harting-Stecker / Multicore-Anschlusskasten".
     */
    public static function suggestName(CableType $type, ArticleCable $cable, ?ConnectorType $a, ?ConnectorType $b): string
    {
        $channels = $cable->multicore !== null ? ' ' . $cable->multicore->channelSummary() : '';

        return trim($type->name . $channels . ' ' . $cable->lengthLabel() . ' ' . ($a?->name ?? '') . ' / ' . ($b?->name ?? ''));
    }
}
