<?php

declare(strict_types=1);

namespace App\Domain\DigitalStagebox;

/** Digitale Protokolle und Stagebox-Angaben der Artikel (Migration 152). */
interface DigitalStageboxRepositoryInterface
{
    /** @return DigitalProtocol[] sortiert */
    public function findAllProtocols(): array;

    public function findProtocolById(string $id): ?DigitalProtocol;

    public function saveProtocol(DigitalProtocol $protocol): void;

    public function deleteProtocol(string $id): void;

    public function protocolNameExists(string $name, ?string $excludeId = null): bool;

    /** @return array<string, int> Protokoll-ID => Anzahl Artikel */
    public function usageCounts(): array;

    public function findByArticle(string $articleId): ?ArticleDigitalStagebox;

    public function saveArticleStagebox(ArticleDigitalStagebox $stagebox): void;

    public function deleteArticleStagebox(string $articleId): void;
}
