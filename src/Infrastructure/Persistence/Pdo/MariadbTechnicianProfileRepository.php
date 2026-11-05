<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Technician\TechnicianProfile;
use App\Domain\Technician\TechnicianProfileRepositoryInterface;
use DateTimeImmutable;
use PDO;

/** Techniker-Profile (Migration 134). */
final class MariadbTechnicianProfileRepository implements TechnicianProfileRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function find(string $userId): ?TechnicianProfile
    {
        $stmt = $this->pdo->prepare('SELECT * FROM technician_profiles WHERE user_id = :id');
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch();

        return $row ? new TechnicianProfile(
            $row['user_id'],
            (bool) $row['requires_confirmation'],
            $row['phone'],
            $row['telegram_chat_id'],
            new DateTimeImmutable($row['updated_at']),
        ) : null;
    }

    public function save(TechnicianProfile $profile): void
    {
        $this->pdo->prepare(
            'INSERT INTO technician_profiles (user_id, requires_confirmation, phone, telegram_chat_id, updated_at)
             VALUES (:user, :confirm, :phone, :telegram, :updated)
             ON DUPLICATE KEY UPDATE requires_confirmation = VALUES(requires_confirmation), phone = VALUES(phone),
                telegram_chat_id = VALUES(telegram_chat_id), updated_at = VALUES(updated_at)'
        )->execute([
            'user' => $profile->userId,
            'confirm' => $profile->requiresConfirmation ? 1 : 0,
            'phone' => $profile->phone,
            'telegram' => $profile->telegramChatId,
            'updated' => $profile->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }
}
