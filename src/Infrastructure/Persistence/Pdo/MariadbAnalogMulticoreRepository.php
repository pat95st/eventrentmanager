<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\AnalogMulticore\AnalogMulticoreRepositoryInterface;
use App\Domain\AnalogMulticore\ArticleAnalogMulticore;
use PDO;

/** Analog multicore cables (migration 156). */
final class MariadbAnalogMulticoreRepository implements AnalogMulticoreRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByArticle(string $articleId): ?ArticleAnalogMulticore
    {
        $stmt = $this->pdo->prepare('SELECT * FROM article_analog_multicores WHERE article_id = :id');
        $stmt->execute(['id' => $articleId]);
        $row = $stmt->fetch();

        return $row ? new ArticleAnalogMulticore(
            $row['article_id'],
            (int) $row['send_count'],
            (int) $row['return_count'],
            (bool) $row['on_drum'],
            (bool) $row['has_harting'],
        ) : null;
    }

    public function save(ArticleAnalogMulticore $multicore): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO article_analog_multicores (article_id, send_count, return_count, on_drum, has_harting)
             VALUES (:article_id, :send_count, :return_count, :on_drum, :has_harting)
             ON DUPLICATE KEY UPDATE send_count = VALUES(send_count), return_count = VALUES(return_count),
                on_drum = VALUES(on_drum), has_harting = VALUES(has_harting)'
        );
        $stmt->execute([
            'article_id' => $multicore->articleId,
            'send_count' => $multicore->sends,
            'return_count' => $multicore->returns,
            'on_drum' => $multicore->onDrum ? 1 : 0,
            'has_harting' => $multicore->hasHarting ? 1 : 0,
        ]);
    }

    public function delete(string $articleId): void
    {
        $this->pdo->prepare('DELETE FROM article_analog_multicores WHERE article_id = :id')->execute(['id' => $articleId]);
    }
}
