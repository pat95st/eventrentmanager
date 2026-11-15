<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Federation\PartnerRentalDiscountLookupInterface;
use App\Domain\Federation\PartnerRequest;
use App\Domain\Federation\PartnerRequestDirection;
use App\Domain\Federation\PartnerRequestItem;
use App\Domain\Federation\PartnerRequestKind;
use App\Domain\Federation\PartnerRequestRepositoryInterface;
use App\Domain\Federation\PartnerRequestStatus;
use DateTimeImmutable;
use PDO;

final class MariadbPartnerRequestRepository implements PartnerRequestRepositoryInterface, PartnerRentalDiscountLookupInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(PartnerRequest $request): void
    {
        $json = static fn ($value) => $value === null || $value === [] ? null : json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $this->pdo->prepare(
            'INSERT INTO partner_requests (id, partner_id, direction, kind, reference, status, event_name, start_date,
                end_date, event_location, note, contact_name, contact_email, items, trades, technician_count,
                preferred_technician, technicians, discount_percent, rental_id, response_message, sync_error,
                created_at, updated_at, responded_at)
             VALUES (:id, :partner_id, :direction, :kind, :reference, :status, :event_name, :start_date,
                :end_date, :event_location, :note, :contact_name, :contact_email, :items, :trades, :technician_count,
                :preferred_technician, :technicians, :discount_percent, :rental_id, :response_message, :sync_error,
                :created_at, :updated_at, :responded_at)
             ON DUPLICATE KEY UPDATE status = VALUES(status), event_name = VALUES(event_name),
                start_date = VALUES(start_date), end_date = VALUES(end_date), event_location = VALUES(event_location),
                note = VALUES(note), items = VALUES(items), trades = VALUES(trades),
                technician_count = VALUES(technician_count), preferred_technician = VALUES(preferred_technician),
                technicians = VALUES(technicians), discount_percent = VALUES(discount_percent),
                rental_id = VALUES(rental_id), response_message = VALUES(response_message),
                sync_error = VALUES(sync_error), updated_at = VALUES(updated_at), responded_at = VALUES(responded_at)',
        )->execute([
            'id' => $request->id,
            'partner_id' => $request->partnerId,
            'direction' => $request->direction->value,
            'kind' => $request->kind->value,
            'reference' => $request->reference,
            'status' => $request->status->value,
            'event_name' => $request->eventName,
            'start_date' => $request->startDate->format('Y-m-d'),
            'end_date' => $request->endDate->format('Y-m-d'),
            'event_location' => $request->eventLocation,
            'note' => $request->note,
            'contact_name' => $request->contactName,
            'contact_email' => $request->contactEmail,
            'items' => $json(array_map(static fn (PartnerRequestItem $item) => $item->toArray(), $request->items)),
            'trades' => $json($request->trades),
            'technician_count' => $request->technicianCount,
            'preferred_technician' => $json($request->preferredTechnician),
            'technicians' => $json($request->technicians),
            'discount_percent' => $request->discountPercent !== null ? number_format($request->discountPercent, 2, '.', '') : null,
            'rental_id' => $request->rentalId,
            'response_message' => $request->responseMessage,
            'sync_error' => $request->syncError,
            'created_at' => $request->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $request->updatedAt->format('Y-m-d H:i:s'),
            'responded_at' => $request->respondedAt?->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?PartnerRequest
    {
        $statement = $this->pdo->prepare('SELECT * FROM partner_requests WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function findByReference(string $partnerId, PartnerRequestDirection $direction, string $reference): ?PartnerRequest
    {
        $statement = $this->pdo->prepare('SELECT * FROM partner_requests WHERE partner_id = :partner_id AND direction = :direction AND reference = :reference');
        $statement->execute(['partner_id' => $partnerId, 'direction' => $direction->value, 'reference' => $reference]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function findRecent(int $limit): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM partner_requests ORDER BY created_at DESC LIMIT :limit');
        $statement->bindValue('limit', max(1, $limit), PDO::PARAM_INT);
        $statement->execute();

        return array_map(fn (array $row) => $this->hydrate($row), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findByRental(string $rentalId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM partner_requests WHERE rental_id = :rental_id ORDER BY created_at DESC');
        $statement->execute(['rental_id' => $rentalId]);

        return array_map(fn (array $row) => $this->hydrate($row), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function countPendingIncoming(): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM partner_requests WHERE direction = 'incoming' AND status = 'pending'")->fetchColumn();
    }

    public function discountPercentForRental(string $rentalId): ?float
    {
        $statement = $this->pdo->prepare(
            "SELECT discount_percent FROM partner_requests
             WHERE rental_id = :rental_id AND direction = 'incoming' AND kind = 'material' AND status = 'accepted'
               AND discount_percent > 0
             ORDER BY created_at DESC LIMIT 1",
        );
        $statement->execute(['rental_id' => $rentalId]);
        $value = $statement->fetchColumn();

        return $value !== false && $value !== null ? (float) $value : null;
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): PartnerRequest
    {
        $decode = static function ($value): array {
            $decoded = json_decode((string) ($value ?? ''), true);

            return is_array($decoded) ? $decoded : [];
        };

        $items = [];
        foreach ($decode($row['items']) as $itemRow) {
            $item = is_array($itemRow) ? PartnerRequestItem::fromArray($itemRow) : null;
            if ($item !== null) {
                $items[] = $item;
            }
        }
        $preferred = $decode($row['preferred_technician']);

        return new PartnerRequest(
            id: (string) $row['id'],
            partnerId: (string) $row['partner_id'],
            direction: PartnerRequestDirection::from((string) $row['direction']),
            kind: PartnerRequestKind::from((string) $row['kind']),
            reference: (string) $row['reference'],
            status: PartnerRequestStatus::tryFrom((string) $row['status']) ?? PartnerRequestStatus::Pending,
            eventName: (string) $row['event_name'],
            startDate: new DateTimeImmutable((string) $row['start_date']),
            endDate: new DateTimeImmutable((string) $row['end_date']),
            eventLocation: $row['event_location'] !== null ? (string) $row['event_location'] : null,
            note: $row['note'] !== null ? (string) $row['note'] : null,
            contactName: $row['contact_name'] !== null ? (string) $row['contact_name'] : null,
            contactEmail: $row['contact_email'] !== null ? (string) $row['contact_email'] : null,
            items: $items,
            trades: array_values(array_map('strval', $decode($row['trades']))),
            technicianCount: (int) $row['technician_count'],
            preferredTechnician: isset($preferred['id'], $preferred['name']) ? ['id' => (string) $preferred['id'], 'name' => (string) $preferred['name']] : null,
            technicians: \App\Application\Federation\PartnerRequestPayload::technicians($decode($row['technicians'])),
            discountPercent: $row['discount_percent'] !== null ? (float) $row['discount_percent'] : null,
            rentalId: $row['rental_id'] !== null ? (string) $row['rental_id'] : null,
            responseMessage: $row['response_message'] !== null ? (string) $row['response_message'] : null,
            syncError: $row['sync_error'] !== null ? (string) $row['sync_error'] : null,
            createdAt: new DateTimeImmutable((string) $row['created_at']),
            updatedAt: new DateTimeImmutable((string) $row['updated_at']),
            respondedAt: $row['responded_at'] !== null ? new DateTimeImmutable((string) $row['responded_at']) : null,
        );
    }
}
