<?php

declare(strict_types=1);

namespace App\Application\ArticleSet;

use App\Domain\ArticleSet\ArticleSetAvailability;
use App\Domain\ArticleSet\ArticleSetRepositoryInterface;
use DateTimeImmutable;

/**
 * Prüft Set- und Upgrade-Positionen (Freitext-Zeilen mit setId bzw.
 * setUpgradeId, Migration 113) in Angebot, AB und Rechnung:
 *
 * - Preis wird immer auf den Festpreis des Sets/Upgrades gesetzt.
 * - Zeitraum muss vorhanden sein, genau die Einsatztage des Sets umfassen
 *   und alle Zeitregeln erfüllen (Nutzerentscheidung: blockieren).
 * - Ein Upgrade braucht das zugehörige Set im selben Dokument.
 * - Verweise auf gelöschte Sets/Upgrades werden entfernt (Zeile bleibt als
 *   normale Freitext-Position erhalten).
 */
final class SetPositionValidator
{
    public function __construct(private readonly ArticleSetRepositoryInterface $sets)
    {
    }

    /**
     * @param array<int, array<string, mixed>> $freeTextItems validierte Zeilen (description, quantity, unitPrice, sortOrder, setId, setUpgradeId)
     * @return array{0: array<string, string>, 1: array<int, array<string, mixed>>}
     */
    public function apply(array $freeTextItems, ?DateTimeImmutable $start, ?DateTimeImmutable $end): array
    {
        $errors = [];
        $setIdsInDocument = [];

        foreach ($freeTextItems as $i => $item) {
            $setId = $item['setId'] ?? null;
            if ($setId === null) {
                continue;
            }
            $set = $this->sets->findById($setId);
            if ($set === null) {
                $freeTextItems[$i]['setId'] = null;
                continue;
            }
            $freeTextItems[$i]['unitPrice'] = $set->price;
            $setIdsInDocument[$set->id] = true;
            $violations = ArticleSetAvailability::violations($set, $this->sets->findRules($set->id), $start, $end);
            if ($violations !== []) {
                $errors['freeTextItems'] = implode(' ', $violations);
            }
        }

        foreach ($freeTextItems as $i => $item) {
            $upgradeId = $item['setUpgradeId'] ?? null;
            if ($upgradeId === null) {
                continue;
            }
            $freeTextItems[$i]['setId'] = null;
            $upgrade = $this->sets->findUpgradeById($upgradeId);
            if ($upgrade === null) {
                $freeTextItems[$i]['setUpgradeId'] = null;
                continue;
            }
            $freeTextItems[$i]['unitPrice'] = $upgrade->price;
            if (!isset($setIdsInDocument[$upgrade->setId])) {
                $errors['freeTextItems'] = "Das Upgrade „{$upgrade->name}“ gehört zu einem Set, das nicht in diesem Dokument enthalten ist.";
            }
        }

        return [$errors, $freeTextItems];
    }
}
