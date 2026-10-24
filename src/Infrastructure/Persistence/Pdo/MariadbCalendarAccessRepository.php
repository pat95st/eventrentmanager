<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Calendar\CalendarAccessRepositoryInterface;
use App\Shared\Uuid;
use PDO;

final class MariadbCalendarAccessRepository implements CalendarAccessRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function userIdsForCalendar(string $calendarId): array
    {
        $stmt = $this->pdo->prepare('SELECT user_id FROM calendar_access WHERE calendar_id = :calendar_id');
        $stmt->execute(['calendar_id' => $calendarId]);

        return array_map(strval(...), $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function hasAccess(string $calendarId, string $userId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM calendar_access WHERE calendar_id = :calendar_id AND user_id = :user_id');
        $stmt->execute(['calendar_id' => $calendarId, 'user_id' => $userId]);

        return $stmt->fetchColumn() !== false;
    }

    public function replaceForCalendar(string $calendarId, array $userIds): void
    {
        // Ersetzen als Transaktion: bestehende Zeilen löschen, dann die neue
        // Liste einfügen — einfacher und weniger fehleranfällig als ein
        // Diff aus alter/neuer Liste zu berechnen (Zugriffslisten sind klein).
        $this->pdo->beginTransaction();
        try {
            $delete = $this->pdo->prepare('DELETE FROM calendar_access WHERE calendar_id = :calendar_id');
            $delete->execute(['calendar_id' => $calendarId]);

            if ($userIds !== []) {
                $insert = $this->pdo->prepare(
                    'INSERT INTO calendar_access (id, calendar_id, user_id) VALUES (:id, :calendar_id, :user_id)'
                );
                foreach (array_unique($userIds) as $userId) {
                    $insert->execute([
                        'id' => Uuid::v4(),
                        'calendar_id' => $calendarId,
                        'user_id' => $userId,
                    ]);
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }
}
