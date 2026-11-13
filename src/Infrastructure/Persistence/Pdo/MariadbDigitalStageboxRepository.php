<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\DigitalStagebox\ArticleDigitalStagebox;
use App\Domain\DigitalStagebox\DigitalProtocol;
use App\Domain\DigitalStagebox\DigitalStageboxRepositoryInterface;
use PDO;

/** Digitale Stageboxen und digitale Protokolle (Migration 152). */
final class MariadbDigitalStageboxRepository implements DigitalStageboxRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findAllProtocols(): array
    {
        return array_map($this->hydrateProtocol(...), $this->pdo->query('SELECT * FROM digital_protocols ORDER BY sort_order, name')->fetchAll());
    }

    public function findProtocolById(string $id): ?DigitalProtocol
    {
        $stmt = $this->pdo->prepare('SELECT * FROM digital_protocols WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrateProtocol($row) : null;
    }

    public function saveProtocol(DigitalProtocol $protocol): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO digital_protocols (id, name, sort_order) VALUES (:id, :name, :sort_order)
             ON DUPLICATE KEY UPDATE name = VALUES(name), sort_order = VALUES(sort_order)'
        );
        $stmt->execute(['id' => $protocol->id, 'name' => $protocol->name, 'sort_order' => $protocol->sortOrder]);
    }

    public function deleteProtocol(string $id): void
    {
        $this->pdo->prepare('DELETE FROM digital_protocols WHERE id = :id')->execute(['id' => $id]);
    }

    public function protocolNameExists(string $name, ?string $excludeId = null): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM digital_protocols WHERE name = :name AND id <> :exclude');
        $stmt->execute(['name' => $name, 'exclude' => $excludeId ?? '']);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function usageCounts(): array
    {
        $counts = [];
        foreach ($this->pdo->query('SELECT protocol_id, COUNT(*) AS cnt FROM article_digital_stageboxes GROUP BY protocol_id')->fetchAll() as $row) {
            $counts[(string) $row['protocol_id']] = (int) $row['cnt'];
        }

        return $counts;
    }

    public function findByArticle(string $articleId): ?ArticleDigitalStagebox
    {
        $stmt = $this->pdo->prepare('SELECT * FROM article_digital_stageboxes WHERE article_id = :id');
        $stmt->execute(['id' => $articleId]);
        $row = $stmt->fetch();

        return $row ? new ArticleDigitalStagebox(
            $row['article_id'],
            $row['protocol_id'],
            (int) $row['inputs_xlr'],
            (int) $row['inputs_combo'],
            (int) $row['outputs_analog'],
            (int) $row['outputs_aes'],
        ) : null;
    }

    public function saveArticleStagebox(ArticleDigitalStagebox $stagebox): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO article_digital_stageboxes (article_id, protocol_id, inputs_xlr, inputs_combo, outputs_analog, outputs_aes)
             VALUES (:article_id, :protocol_id, :inputs_xlr, :inputs_combo, :outputs_analog, :outputs_aes)
             ON DUPLICATE KEY UPDATE protocol_id = VALUES(protocol_id), inputs_xlr = VALUES(inputs_xlr),
                inputs_combo = VALUES(inputs_combo), outputs_analog = VALUES(outputs_analog), outputs_aes = VALUES(outputs_aes)'
        );
        $stmt->execute([
            'article_id' => $stagebox->articleId,
            'protocol_id' => $stagebox->protocolId,
            'inputs_xlr' => $stagebox->inputsXlr,
            'inputs_combo' => $stagebox->inputsCombo,
            'outputs_analog' => $stagebox->outputsAnalog,
            'outputs_aes' => $stagebox->outputsAes,
        ]);
    }

    public function deleteArticleStagebox(string $articleId): void
    {
        $this->pdo->prepare('DELETE FROM article_digital_stageboxes WHERE article_id = :id')->execute(['id' => $articleId]);
    }

    private function hydrateProtocol(array $row): DigitalProtocol
    {
        return new DigitalProtocol($row['id'], $row['name'], (int) $row['sort_order']);
    }
}
