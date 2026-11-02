<?php

declare(strict_types=1);

namespace App\Domain\Settings;

/**
 * Starttexte je Dokumenttyp (Migration 113): 'offer', 'order_confirmation',
 * 'invoice'. Klartext mit Platzhaltern, siehe DocumentIntroTextRenderer.
 */
interface DocumentIntroTextRepositoryInterface
{
    public const TYPES = ['offer', 'order_confirmation', 'invoice'];

    /** @return array<string, string> Dokumenttyp => Text (nur gesetzte) */
    public function getAll(): array;

    public function get(string $documentType): ?string;

    public function save(string $documentType, ?string $text): void;
}
