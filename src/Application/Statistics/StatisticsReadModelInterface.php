<?php

declare(strict_types=1);

namespace App\Application\Statistics;

use DateTimeImmutable;

/** Lesemodell für die Dashboard-Statistiken (reine Aggregat-Abfragen). */
interface StatisticsReadModelInterface
{
    /** @return array<string, int> "YYYY-MM" => Anzahl Veranstaltungen (nach Beginn) */
    public function rentalsPerMonth(DateTimeImmutable $from, DateTimeImmutable $to): array;

    /** @return list<array{articleId: string, quantity: int, rentals: int}> nach Menge absteigend */
    public function topArticles(DateTimeImmutable $from, DateTimeImmutable $to, int $limit): array;

    /** @return list<array{categoryId: ?string, name: string, count: int}> Exemplare im Bestand je Kategorie, absteigend */
    public function stockByCategory(): array;

    /** @return array<string, int> Angebotsstatus => Anzahl (angelegt ab $from) */
    public function offersByStatus(DateTimeImmutable $from): array;

    /**
     * Prüfpflichtige Exemplare (nicht verkauft) mit Fälligkeit der letzten Prüfung.
     *
     * @return list<array{inventoryNumberId: string, number: string, articleId: string, dueDate: ?string, passed: ?bool}>
     */
    public function inspectionDueDates(): array;
}
