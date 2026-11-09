<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\EventAudio\RentalAudioSettings;
use App\Domain\EventAudio\RentalAudioSettingsRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbRentalAudioSettingsRepository implements RentalAudioSettingsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(string $rentalId): RentalAudioSettings
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rental_audio_settings WHERE rental_id = :id');
        $stmt->execute(['id' => $rentalId]);
        $row = $stmt->fetch();

        return $row
            ? new RentalAudioSettings($rentalId, $row['player_id'], (bool) $row['gong_by_default'])
            : new RentalAudioSettings($rentalId);
    }

    public function save(RentalAudioSettings $settings): void
    {
        $this->pdo->prepare(
            'INSERT INTO rental_audio_settings (rental_id, player_id, gong_by_default, updated_at) VALUES (:id, :player, :gong, NOW())
             ON DUPLICATE KEY UPDATE player_id = VALUES(player_id), gong_by_default = VALUES(gong_by_default), updated_at = NOW()'
        )->execute([
            'id' => $settings->rentalId,
            'player' => $settings->playerId,
            'gong' => $settings->gongByDefault ? 1 : 0,
        ]);
    }

    public function activeRentalIdForPlayer(string $playerId, DateTimeImmutable $now): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.id FROM rental_audio_settings s JOIN rentals r ON r.id = s.rental_id
             WHERE s.player_id = :player
               AND DATE_SUB(r.start_date, INTERVAL 1 DAY) <= :today
               AND DATE_ADD(r.end_date, INTERVAL 1 DAY) >= :today2
             ORDER BY r.start_date ASC LIMIT 1'
        );
        $stmt->execute(['player' => $playerId, 'today' => $now->format('Y-m-d'), 'today2' => $now->format('Y-m-d')]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (string) $id : null;
    }
}
