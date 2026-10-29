<?php

declare(strict_types=1);

namespace App\Domain\Article;

interface ArticleRepositoryInterface
{
    public function save(Article $article): void;

    public function findById(string $id): ?Article;

    /**
     * @param string|null $categoryId Wenn gesetzt, wird inkl. Unterkategorien gefiltert.
     * @param string[] $categoryIds Vorab aufgelöste Kategorie-ID + Nachfahren (für den Filter).
     * @param bool $onlyActive true = deaktivierte Artikel werden ausgeschlossen (Artikelauswahl beim Erstellen
     *        von Angeboten/Rechnungen/Veranstaltungspositionen/Case-Inhalten); false = alle Artikel, unabhängig
     *        vom Status (Artikelverwaltung selbst, siehe ArticleController::index).
     * @return Article[] Höchstens $limit Zeilen, ab $offset (siehe countSearch() für die Gesamtanzahl).
     */
    /** @param string[] $manufacturerIds zusätzlicher Mehrfachfilter (ODER-verknüpft), siehe ListArticles::$manufacturerIds */
    public function search(?string $searchTerm, ?string $manufacturerId, array $categoryIds, int $limit, int $offset, bool $onlyActive = false, array $manufacturerIds = [], bool $onlyRentableIndividually = false, bool $excludeBundles = false): array;

    /** Gesamtanzahl der Treffer für dieselben Filter wie search(), ohne LIMIT/OFFSET (für die Paginierung). */
    /** @param string[] $manufacturerIds siehe search() */
    public function countSearch(?string $searchTerm, ?string $manufacturerId, array $categoryIds, bool $onlyActive = false, array $manufacturerIds = [], bool $onlyRentableIndividually = false, bool $excludeBundles = false): int;

    public function countByCategory(string $categoryId): int;

    public function countByManufacturer(string $manufacturerId): int;

    /** Prüft, ob innerhalb eines Herstellers bereits ein Artikel dieses Präfix nutzt. */
    public function existsByPrefix(string $manufacturerId, string $prefix, ?string $excludeId = null): bool;

    public function delete(string $id): void;

    public function addImage(ArticleImage $image): void;

    public function deleteImage(string $imageId): ?ArticleImage;

    public function findImageById(string $imageId): ?ArticleImage;
}
