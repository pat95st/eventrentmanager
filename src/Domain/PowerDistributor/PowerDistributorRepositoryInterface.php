<?php

declare(strict_types=1);

namespace App\Domain\PowerDistributor;

interface PowerDistributorRepositoryInterface
{
    public function findByArticle(string $articleId): ?PowerDistributor;

    /** Speichert Kopf, Schutzorgane und Abgänge vollständig (ersetzt die bisherigen). */
    public function save(PowerDistributor $distributor): void;

    public function delete(string $articleId): void;

    /** @return string[] Artikel-IDs, die Stromverteiler sind */
    public function articleIds(): array;
}
