<?php

declare(strict_types=1);

namespace App\Domain\DocumentStorage;

interface DocumentStorageAssignmentRepositoryInterface
{
    public function save(DocumentStorageAssignment $assignment): void;

    public function findById(string $id): ?DocumentStorageAssignment;

    /** @return DocumentStorageAssignment[] */
    public function findAll(): array;

    /** @return DocumentStorageAssignment[] Nur enabled=true-Zuordnungen für den angegebenen Dokumenttyp, siehe DocumentStoragePusher. */
    public function findEnabledForDocumentType(DocumentType $documentType): array;

    public function delete(string $id): void;
}
