<?php

declare(strict_types=1);

namespace App\Application\Common;

/**
 * Generisches Ergebnis-Objekt für seitenweise geladene Listen (Artikel,
 * Veranstaltungen, Kunden, ...). Wird von allen "List*"-QueryHandlern
 * anstelle eines rohen Arrays zurückgegeben, damit Controller/Views
 * Seite, Seitengröße und Gesamtanzahl einheitlich auswerten können, ohne
 * dass jede Funktionalität ihre eigene Paginierungs-Form erfindet.
 *
 * @template T
 */
final class PaginatedResult
{
    /**
     * @param T[] $items Zeilen der aktuellen Seite (bereits LIMIT/OFFSET-begrenzt).
     * @param int $totalCount Gesamtanzahl der Treffer ohne LIMIT/OFFSET (inkl. aktiver Filter).
     * @param int $page 1-indexierte aktuelle Seite.
     * @param int $pageSize Angeforderte Seitengröße (> 0).
     */
    public function __construct(
        public readonly array $items,
        public readonly int $totalCount,
        public readonly int $page,
        public readonly int $pageSize,
    ) {
    }

    /**
     * Gesamtzahl der Seiten. Liefert bei 0 Treffern bewusst 1 (statt 0),
     * damit "Seite 1 von 1" angezeigt werden kann, statt einer Division
     * ohne sinnvolle Seite.
     */
    public function totalPages(): int
    {
        if ($this->pageSize <= 0 || $this->totalCount <= 0) {
            return 1;
        }

        return (int) ceil($this->totalCount / $this->pageSize);
    }

    public function hasPrevious(): bool
    {
        return $this->page > 1;
    }

    public function hasNext(): bool
    {
        return $this->page < $this->totalPages();
    }

    /** Berechnet den SQL-OFFSET für eine Seite/Seitengröße (1-indexiert). */
    public static function offsetFor(int $page, int $pageSize): int
    {
        $page = max(1, $page);
        $pageSize = max(1, $pageSize);

        return ($page - 1) * $pageSize;
    }
}
