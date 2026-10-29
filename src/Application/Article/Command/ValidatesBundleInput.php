<?php

declare(strict_types=1);

namespace App\Application\Article\Command;

use App\Domain\Article\ArticleBundleComponent;
use App\Domain\Article\ArticleBundleComponentRepositoryInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Rental\RentalBundleRepositoryInterface;
use App\Shared\Uuid;

/**
 * Gemeinsame Prüfung der Bundle-Angaben für Create-/UpdateArticleHandler
 * (Migration 107).
 *
 * Regeln:
 * - Ein Bundle braucht mindestens einen Bestandteil, jeder mit Menge ≥ 1.
 * - Bestandteile müssen existierende, nicht selbst gebündelte Artikel sein,
 *   nicht das Bundle selbst, jeder Artikel höchstens einmal.
 * - Ein Bundle wird zum eigenen Bundle-Preis abgerechnet und braucht daher
 *   "als eigener Posten abrechnen" plus Tagesmietsatz.
 * - Ein Artikel, der Bestandteil eines anderen Bundles ist, kann selbst
 *   kein Bundle werden (keine verschachtelten Bundles).
 * - Ein Bundle, das in Vermietungen gebucht ist, kann nicht wieder zu einem
 *   normalen Artikel gemacht werden.
 * - Migration 111: je Bestandteil optionale Alternativartikel (gleiche
 *   Regeln wie Bestandteile, nicht der Bestandteil selbst, keine Doppelten).
 *   Ein Artikel mit Inventarnummern kann kein Bundle werden.
 */
final class ValidatesBundleInput
{
    /**
     * @param array<int, array{article_id?: string, quantity?: string|int, alternative_ids?: string}> $rawComponents alternative_ids: kommagetrennte Artikel-IDs
     * @return array{0: array<string, string>, 1: ArticleBundleComponent[]} [errors, components]
     */
    public static function validate(
        bool $isBundle,
        bool $isBillable,
        ?string $dailyRentalPrice,
        array $rawComponents,
        ?string $selfId,
        bool $wasBundle,
        ArticleRepositoryInterface $articles,
        ?ArticleBundleComponentRepositoryInterface $bundleComponents,
        ?RentalBundleRepositoryInterface $rentalBundles,
        bool $hasInventoryNumbers = false,
    ): array {
        $errors = [];
        $components = [];

        if (!$isBundle) {
            if ($wasBundle && $selfId !== null && $rentalBundles !== null && $rentalBundles->existsForBundle($selfId)) {
                $errors['isBundle'] = 'Dieses Bundle ist in Vermietungen gebucht und kann daher nicht in einen normalen Artikel umgewandelt werden.';
            }

            return [$errors, []];
        }

        if ($selfId !== null && $bundleComponents !== null && ($bundleComponents->findBundleIdsContaining($selfId) !== [] || $bundleComponents->findBundleIdsWithAlternative($selfId) !== [])) {
            $errors['isBundle'] = 'Dieser Artikel ist Bestandteil eines anderen Bundles und kann daher selbst kein Bundle sein.';
        }

        if ($hasInventoryNumbers) {
            $errors['isBundle'] = 'Dieser Artikel hat Inventarnummern — Bundles haben keine eigenen Inventarnummern. Bitte ein neues Bundle anlegen.';
        }

        if (!$isBillable || $dailyRentalPrice === null || trim($dailyRentalPrice) === '') {
            $errors['dailyRentalPrice'] = 'Ein Bundle wird zum eigenen Bundle-Preis abgerechnet — bitte „als eigener Posten abrechnen“ aktivieren und einen Preis pro Einsatztag angeben.';
        }

        $seen = [];
        $sortOrder = 0;
        foreach ($rawComponents as $row) {
            $articleId = trim((string) ($row['article_id'] ?? ''));
            $rawQuantity = trim((string) ($row['quantity'] ?? ''));
            if ($articleId === '' && $rawQuantity === '') {
                continue;
            }

            $quantity = filter_var($rawQuantity === '' ? '1' : $rawQuantity, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 999]]);
            $component = $articleId !== '' ? $articles->findById($articleId) : null;

            if ($component === null) {
                $errors['bundleComponents'] = 'Bitte für jeden Bestandteil einen gültigen Artikel auswählen.';
                continue;
            }
            if ($articleId === $selfId) {
                $errors['bundleComponents'] = 'Ein Bundle kann sich nicht selbst enthalten.';
                continue;
            }
            if ($component->isBundle) {
                $errors['bundleComponents'] = "„{$component->name}“ ist selbst ein Bundle — Bundles können nicht verschachtelt werden.";
                continue;
            }
            if (isset($seen[$articleId])) {
                $errors['bundleComponents'] = "„{$component->name}“ ist mehrfach eingetragen — bitte stattdessen die Menge erhöhen.";
                continue;
            }
            if ($quantity === false) {
                $errors['bundleComponents'] = 'Bitte für jeden Bestandteil eine Menge zwischen 1 und 999 angeben.';
                continue;
            }

            $alternativeIds = [];
            foreach (explode(',', (string) ($row['alternative_ids'] ?? '')) as $rawAlternativeId) {
                $alternativeId = trim($rawAlternativeId);
                if ($alternativeId === '' || in_array($alternativeId, $alternativeIds, true)) {
                    continue;
                }
                $alternative = $articles->findById($alternativeId);
                if ($alternative === null) {
                    $errors['bundleComponents'] = 'Eine gewählte Alternative existiert nicht mehr — bitte die Alternativen prüfen.';
                    continue;
                }
                if ($alternativeId === $articleId || $alternativeId === $selfId) {
                    $errors['bundleComponents'] = "„{$alternative->name}“ kann nicht Alternative zu sich selbst bzw. zum Bundle sein.";
                    continue;
                }
                if ($alternative->isBundle) {
                    $errors['bundleComponents'] = "„{$alternative->name}“ ist selbst ein Bundle und kann nicht als Alternative dienen.";
                    continue;
                }
                $alternativeIds[] = $alternativeId;
            }

            $seen[$articleId] = true;
            $components[] = new ArticleBundleComponent(
                id: Uuid::v4(),
                bundleArticleId: $selfId ?? '',
                componentArticleId: $articleId,
                quantity: (int) $quantity,
                sortOrder: $sortOrder++,
                alternativeArticleIds: $alternativeIds,
            );
        }

        if ($components === [] && !isset($errors['bundleComponents'])) {
            $errors['bundleComponents'] = 'Ein Bundle braucht mindestens einen Bestandteil.';
        }

        return [$errors, $components];
    }
}
