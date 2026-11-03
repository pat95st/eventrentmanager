<?php

declare(strict_types=1);

namespace App\Domain\Settings;

interface PdfFontRepositoryInterface
{
    /** @return PdfFont[] nach Name sortiert */
    public function findAll(): array;

    public function findById(string $id): ?PdfFont;

    public function findByFamily(string $family): ?PdfFont;

    public function save(PdfFont $font): void;

    public function delete(string $id): void;

    /** @return array<string, string> Rolle => Schrift-ID (nur zugeordnete) */
    public function assignments(): array;

    public function assign(string $role, ?string $fontId): void;
}
