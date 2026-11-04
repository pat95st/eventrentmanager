<?php

declare(strict_types=1);

namespace App\Application\Cable;

use App\Domain\Cable\ArticleCable;
use App\Domain\Cable\CableRepositoryInterface;
use App\Domain\Cable\CableType;
use App\Domain\Cable\ConnectorType;

/**
 * Prüft die Leitungsangaben eines Artikels (Migration 125): Leitungstyp,
 * Länge (0,1–1000 m) und zwei Steckverbinder, die für diesen Leitungstyp
 * erlaubt sind (Einstellungen › Leitungen).
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

        if ($errors !== []) {
            return [$errors, null, $type];
        }

        return [[], new ArticleCable($articleId, $type->id, number_format((float) $raw, 2, '.', ''), (string) $input->connectorAId, (string) $input->connectorBId), $type];
    }

    /** Vorschlag für den Artikelnamen, z. B. "H07RN-F 5G6 10 m CEE 32 A Stecker rot / CEE 32 A Kupplung rot". */
    public static function suggestName(CableType $type, ArticleCable $cable, ?ConnectorType $a, ?ConnectorType $b): string
    {
        return trim($type->name . ' ' . $cable->lengthLabel() . ' ' . ($a?->name ?? '') . ' / ' . ($b?->name ?? ''));
    }
}
