<?php

declare(strict_types=1);

namespace App\Application\ArticleSet\Command;

use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\ArticleSet\ArticleSetItem;
use App\Domain\ArticleSet\ArticleSetRule;
use App\Domain\ArticleSet\ArticleSetRuleType;
use App\Domain\ArticleSet\ArticleSetUpgrade;
use App\Domain\ArticleSet\ArticleSetUpgradeItem;
use App\Shared\Money;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Gemeinsame Prüfung für Create-/UpdateArticleSetHandler.
 *
 * - Name, Festpreis und Einsatztage (1–365) sind Pflicht.
 * - Mindestens ein Artikel/Bundle mit Menge 1–999, jeder höchstens einmal;
 *   "nicht einzeln vermietbare" Artikel nur über ihr Bundle.
 * - Regeln: Wochenende, Werktage, jährlicher Zeitraum (TT.MM. bis TT.MM.,
 *   auch über den Jahreswechsel), fester Zeitraum (Datum von/bis).
 * - Upgrades: Name, Preis, mindestens ein Artikel mit Menge. Bestehende
 *   Upgrade-IDs bleiben erhalten (Dokumentpositionen verweisen darauf).
 */
final class ValidatesArticleSetInput
{
    /**
     * @param array<int, array{article_id?: string, quantity?: string}> $rawItems
     * @param array<int, array{type?: string, from_month_day?: string, to_month_day?: string, from_date?: string, to_date?: string}> $rawRules
     * @param array<int, array{id?: string, name?: string, price?: string, items?: array<int, array{article_id?: string, quantity?: string}>}> $rawUpgrades
     * @param string[] $existingUpgradeIds
     * @return array{0: array<string, string>, 1: array{price: string, deploymentDays: int, items: ArticleSetItem[], rules: ArticleSetRule[], upgrades: ArticleSetUpgrade[]}}
     */
    public static function validate(
        string $setId,
        string $name,
        ?string $price,
        ?string $deploymentDays,
        array $rawItems,
        array $rawRules,
        array $rawUpgrades,
        array $existingUpgradeIds,
        ArticleRepositoryInterface $articles,
    ): array {
        $errors = [];

        if (trim($name) === '') {
            $errors['name'] = 'Bitte einen Namen für das Set angeben.';
        }

        $normalizedPrice = Money::normalize($price);
        if ($normalizedPrice === null || $normalizedPrice === false) {
            $errors['price'] = 'Bitte einen gültigen Festpreis angeben (z. B. 249.00).';
            $normalizedPrice = '0.00';
        }

        $days = filter_var(trim((string) $deploymentDays), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 365]]);
        if ($days === false) {
            $errors['deploymentDays'] = 'Bitte die Anzahl der Einsatztage angeben (1 bis 365).';
            $days = 1;
        }

        [$itemErrors, $articleRows] = self::articleRows($rawItems, $articles, 'items');
        $errors = array_merge($errors, $itemErrors);
        if ($articleRows === [] && !isset($errors['items'])) {
            $errors['items'] = 'Ein Set braucht mindestens einen Artikel oder ein Bundle.';
        }
        $items = [];
        foreach ($articleRows as $i => [$articleId, $quantity]) {
            $items[] = new ArticleSetItem(Uuid::v4(), $setId, $articleId, $quantity, $i);
        }

        $rules = [];
        foreach (array_values($rawRules) as $i => $rawRule) {
            $type = ArticleSetRuleType::tryFrom(trim((string) ($rawRule['type'] ?? '')));
            if ($type === null) {
                continue; // leere Zeile
            }
            $fromMd = $toMd = null;
            $fromDate = $toDate = null;
            if ($type === ArticleSetRuleType::YearlyPeriod) {
                $fromMd = self::monthDay((string) ($rawRule['from_month_day'] ?? ''));
                $toMd = self::monthDay((string) ($rawRule['to_month_day'] ?? ''));
                if ($fromMd === null || $toMd === null) {
                    $errors['rules'] = 'Bitte beim jährlichen Zeitraum Beginn und Ende als TT.MM. angeben (z. B. 01.05. bis 30.09.).';
                    continue;
                }
            }
            if ($type === ArticleSetRuleType::DateRange) {
                $fromDate = self::date((string) ($rawRule['from_date'] ?? ''));
                $toDate = self::date((string) ($rawRule['to_date'] ?? ''));
                if ($fromDate === null || $toDate === null || $toDate < $fromDate) {
                    $errors['rules'] = 'Bitte beim festen Zeitraum ein gültiges Von- und Bis-Datum angeben (Bis nicht vor Von).';
                    continue;
                }
            }
            $rules[] = new ArticleSetRule(Uuid::v4(), $setId, $type, $fromMd, $toMd, $fromDate, $toDate, $i);
        }

        $upgrades = [];
        foreach (array_values($rawUpgrades) as $i => $rawUpgrade) {
            $upgradeName = trim((string) ($rawUpgrade['name'] ?? ''));
            $upgradeRawItems = (array) ($rawUpgrade['items'] ?? []);
            $hasAnyArticle = array_filter($upgradeRawItems, static fn ($row) => trim((string) ($row['article_id'] ?? '')) !== '') !== [];
            if ($upgradeName === '' && trim((string) ($rawUpgrade['price'] ?? '')) === '' && !$hasAnyArticle) {
                continue; // leere Zeile
            }
            if ($upgradeName === '') {
                $errors['upgrades'] = 'Bitte für jedes Upgrade einen Namen angeben.';
                continue;
            }
            $upgradePrice = Money::normalize((string) ($rawUpgrade['price'] ?? ''));
            if ($upgradePrice === null || $upgradePrice === false) {
                $errors['upgrades'] = "Bitte für das Upgrade „{$upgradeName}“ einen gültigen Preis angeben.";
                continue;
            }
            [$upgradeErrors, $upgradeRows] = self::articleRows($upgradeRawItems, $articles, 'upgrades');
            if ($upgradeErrors !== []) {
                $errors = array_merge($errors, $upgradeErrors);
                continue;
            }
            if ($upgradeRows === []) {
                $errors['upgrades'] = "Das Upgrade „{$upgradeName}“ braucht mindestens einen Artikel.";
                continue;
            }
            $rawId = trim((string) ($rawUpgrade['id'] ?? ''));
            $upgradeId = in_array($rawId, $existingUpgradeIds, true) ? $rawId : Uuid::v4();
            $upgradeItems = [];
            foreach ($upgradeRows as [$articleId, $quantity]) {
                $upgradeItems[] = new ArticleSetUpgradeItem(Uuid::v4(), $upgradeId, $articleId, $quantity);
            }
            $upgrades[] = new ArticleSetUpgrade($upgradeId, $setId, $upgradeName, $upgradePrice, $i, $upgradeItems);
        }

        return [$errors, ['price' => $normalizedPrice, 'deploymentDays' => (int) $days, 'items' => $items, 'rules' => $rules, 'upgrades' => $upgrades]];
    }

    /**
     * @param array<int, array{article_id?: string, quantity?: string}> $rawRows
     * @return array{0: array<string, string>, 1: list<array{0: string, 1: int}>}
     */
    private static function articleRows(array $rawRows, ArticleRepositoryInterface $articles, string $errorKey): array
    {
        $errors = [];
        $rows = [];
        $seen = [];
        foreach ($rawRows as $row) {
            $articleId = trim((string) ($row['article_id'] ?? ''));
            if ($articleId === '') {
                continue;
            }
            $article = $articles->findById($articleId);
            if ($article === null) {
                $errors[$errorKey] = 'Bitte für jede Zeile einen gültigen Artikel auswählen.';
                continue;
            }
            if (!$article->isBundle && !$article->isRentableIndividually) {
                $errors[$errorKey] = "„{$article->name}“ ist nicht einzeln vermietbar — bitte das passende Bundle wählen.";
                continue;
            }
            if (isset($seen[$articleId])) {
                $errors[$errorKey] = "„{$article->name}“ ist mehrfach eingetragen — bitte stattdessen die Menge erhöhen.";
                continue;
            }
            $quantity = filter_var(trim((string) ($row['quantity'] ?? '1')) ?: '1', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 999]]);
            if ($quantity === false) {
                $errors[$errorKey] = 'Bitte für jeden Artikel eine Menge zwischen 1 und 999 angeben.';
                continue;
            }
            $seen[$articleId] = true;
            $rows[] = [$articleId, (int) $quantity];
        }

        return [$errors, $rows];
    }

    /** "01.05." / "1.5" / "01.05" -> "05-01" */
    private static function monthDay(string $raw): ?string
    {
        if (preg_match('/^\s*(\d{1,2})\.(\d{1,2})\.?\s*$/', $raw, $m) !== 1) {
            return null;
        }
        $day = (int) $m[1];
        $month = (int) $m[2];
        if (!checkdate($month, $day, 2024)) { // Schaltjahr, damit 29.02. erlaubt ist
            return null;
        }

        return sprintf('%02d-%02d', $month, $day);
    }

    private static function date(string $raw): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', trim($raw));

        return $date !== false ? $date : null;
    }
}
