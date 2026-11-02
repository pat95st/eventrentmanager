<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Settings\DocumentIntroTextRepositoryInterface;
use PDO;

final class MariadbDocumentIntroTextRepository implements DocumentIntroTextRepositoryInterface
{
    /** @var array<string, string>|null */
    private ?array $cache = null;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function getAll(): array
    {
        if ($this->cache === null) {
            $stmt = $this->pdo->prepare('SELECT document_type, text FROM document_intro_texts WHERE text IS NOT NULL');
            $stmt->execute();
            $this->cache = [];
            foreach ($stmt->fetchAll() as $row) {
                $this->cache[(string) $row['document_type']] = (string) $row['text'];
            }
        }

        return $this->cache;
    }

    public function get(string $documentType): ?string
    {
        return $this->getAll()[$documentType] ?? null;
    }

    public function save(string $documentType, ?string $text): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO document_intro_texts (document_type, text) VALUES (:document_type, :text)
             ON DUPLICATE KEY UPDATE text = VALUES(text)'
        );
        $stmt->execute(['document_type' => $documentType, 'text' => $text]);
        $this->cache = null;
    }
}
