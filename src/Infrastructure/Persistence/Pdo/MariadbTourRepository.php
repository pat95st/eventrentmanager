<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Tour\TourAllocation;
use App\Domain\Tour\TourCrewAction;
use App\Domain\Tour\TourRepositoryInterface;
use App\Domain\Tour\TourSavedAddress;
use App\Domain\Tour\TourStop;
use App\Domain\Tour\TourStopItem;
use App\Domain\Tour\TourStopKind;
use App\Domain\Tour\TourStopTechnician;
use App\Domain\Tour\WaypointAddressType;
use App\Domain\Tour\WaypointPurpose;
use App\Shared\Uuid;
use DateTimeImmutable;
use PDO;

final class MariadbTourRepository implements TourRepositoryInterface
{
    private const STOP_COLUMNS = [
        'id', 'rental_id', 'kind', 'venue_id', 'location_text', 'setup_at', 'event_start_at', 'event_end_at', 'teardown_end_at',
        'contact_name', 'contact_phone', 'note', 'after_stop_id', 'purpose', 'address_type', 'warehouse_id', 'saved_address_id',
        'label', 'street', 'postal_code', 'city', 'country', 'latitude', 'longitude', 'arrive_at', 'depart_at', 'arrival_not_billed', 'arrival_km_override',
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findStopsByRental(string $rentalId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rental_tour_stops WHERE rental_id = :id ORDER BY event_start_at, created_at');
        $stmt->execute(['id' => $rentalId]);

        return array_map($this->hydrateStop(...), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findStop(string $id): ?TourStop
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rental_tour_stops WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrateStop($row) : null;
    }

    public function saveStop(TourStop $stop): void
    {
        $dt = static fn (?DateTimeImmutable $d): ?string => $d?->format('Y-m-d H:i:s');
        $values = [
            'id' => $stop->id, 'rental_id' => $stop->rentalId, 'kind' => $stop->kind->value,
            'venue_id' => $stop->venueId, 'location_text' => $stop->locationText,
            'setup_at' => $dt($stop->setupAt), 'event_start_at' => $dt($stop->eventStartAt),
            'event_end_at' => $dt($stop->eventEndAt), 'teardown_end_at' => $dt($stop->teardownEndAt),
            'contact_name' => $stop->contactName, 'contact_phone' => $stop->contactPhone, 'note' => $stop->note,
            'after_stop_id' => $stop->afterStopId, 'purpose' => $stop->purpose?->value, 'address_type' => $stop->addressType?->value,
            'warehouse_id' => $stop->warehouseId, 'saved_address_id' => $stop->savedAddressId, 'label' => $stop->label,
            'street' => $stop->street, 'postal_code' => $stop->postalCode, 'city' => $stop->city, 'country' => $stop->country,
            'latitude' => $stop->latitude, 'longitude' => $stop->longitude,
            'arrive_at' => $dt($stop->arriveAt), 'depart_at' => $dt($stop->departAt),
            'arrival_not_billed' => $stop->arrivalNotBilled ? 1 : 0,
            'arrival_km_override' => $stop->arrivalKmOverride,
        ];
        $updates = implode(', ', array_map(static fn (string $c) => "{$c} = VALUES({$c})", array_slice(self::STOP_COLUMNS, 2)));
        $sql = 'INSERT INTO rental_tour_stops (' . implode(', ', self::STOP_COLUMNS) . ') VALUES (:' . implode(', :', self::STOP_COLUMNS) . ')'
            . ' ON DUPLICATE KEY UPDATE ' . $updates;
        $this->pdo->prepare($sql)->execute($values);
    }

    public function deleteStop(string $id): void
    {
        $this->pdo->prepare('DELETE FROM rental_tour_stops WHERE id = :id')->execute(['id' => $id]);
    }

    public function findItemsByRental(string $rentalId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT i.* FROM rental_tour_stop_items i INNER JOIN rental_tour_stops s ON s.id = i.stop_id WHERE s.rental_id = :id'
        );
        $stmt->execute(['id' => $rentalId]);

        return array_map($this->hydrateItem(...), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findItem(string $stopId, string $articleId): ?TourStopItem
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rental_tour_stop_items WHERE stop_id = :stop AND article_id = :article');
        $stmt->execute(['stop' => $stopId, 'article' => $articleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrateItem($row) : null;
    }

    public function saveItem(TourStopItem $item): void
    {
        $this->pdo->prepare(
            'INSERT INTO rental_tour_stop_items (id, stop_id, article_id, delta, allocation, issue_waypoint_id, return_waypoint_id)
             VALUES (:id, :stop_id, :article_id, :delta, :allocation, :issue, :return)
             ON DUPLICATE KEY UPDATE delta = VALUES(delta), allocation = VALUES(allocation),
                issue_waypoint_id = VALUES(issue_waypoint_id), return_waypoint_id = VALUES(return_waypoint_id)'
        )->execute([
            'id' => $item->id, 'stop_id' => $item->stopId, 'article_id' => $item->articleId, 'delta' => $item->delta,
            'allocation' => $item->allocation->value, 'issue' => $item->issueWaypointId, 'return' => $item->returnWaypointId,
        ]);
    }

    public function deleteItem(string $id): void
    {
        $this->pdo->prepare('DELETE FROM rental_tour_stop_items WHERE id = :id')->execute(['id' => $id]);
    }

    public function findTechniciansByRental(string $rentalId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.* FROM rental_tour_stop_technicians t INNER JOIN rental_tour_stops s ON s.id = t.stop_id
             WHERE s.rental_id = :id ORDER BY t.created_at'
        );
        $stmt->execute(['id' => $rentalId]);

        return array_map(static fn (array $r) => new TourStopTechnician(
            (string) $r['id'],
            (string) $r['stop_id'],
            TourCrewAction::from((string) $r['action']),
            (string) $r['user_id'],
            $r['replaces_user_id'],
        ), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function saveTechnician(TourStopTechnician $technician): void
    {
        $this->pdo->prepare(
            'INSERT INTO rental_tour_stop_technicians (id, stop_id, action, user_id, replaces_user_id)
             VALUES (:id, :stop, :action, :user, :replaces)'
        )->execute([
            'id' => $technician->id, 'stop' => $technician->stopId, 'action' => $technician->action->value,
            'user' => $technician->userId, 'replaces' => $technician->replacesUserId,
        ]);
    }

    public function deleteTechnician(string $id): void
    {
        $this->pdo->prepare('DELETE FROM rental_tour_stop_technicians WHERE id = :id')->execute(['id' => $id]);
    }

    public function findSavedAddresses(): array
    {
        return array_map($this->hydrateAddress(...), $this->pdo->query('SELECT * FROM tour_saved_addresses ORDER BY label')->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findSavedAddress(string $id): ?TourSavedAddress
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tour_saved_addresses WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrateAddress($row) : null;
    }

    public function saveSavedAddress(TourSavedAddress $address): void
    {
        $this->pdo->prepare(
            'INSERT INTO tour_saved_addresses (id, label, street, postal_code, city, country, latitude, longitude)
             VALUES (:id, :label, :street, :postal_code, :city, :country, :latitude, :longitude)
             ON DUPLICATE KEY UPDATE label = VALUES(label), street = VALUES(street), postal_code = VALUES(postal_code),
                city = VALUES(city), country = VALUES(country), latitude = VALUES(latitude), longitude = VALUES(longitude)'
        )->execute([
            'id' => $address->id, 'label' => $address->label, 'street' => $address->street, 'postal_code' => $address->postalCode,
            'city' => $address->city, 'country' => $address->country, 'latitude' => $address->latitude, 'longitude' => $address->longitude,
        ]);
    }

    public function syncExtraQuantities(string $rentalId, array $extraByArticle): void
    {
        $this->pdo->prepare('UPDATE rental_items SET tour_extra_quantity = 0 WHERE rental_id = :id')->execute(['id' => $rentalId]);
        $insert = $this->pdo->prepare(
            'INSERT INTO rental_items (id, rental_id, article_id, quantity, tour_extra_quantity, accessory_checked, created_at, updated_at)
             VALUES (:id, :rental, :article, 0, :extra, 0, NOW(), NOW())
             ON DUPLICATE KEY UPDATE tour_extra_quantity = VALUES(tour_extra_quantity)'
        );
        foreach ($extraByArticle as $articleId => $extra) {
            if ($extra > 0) {
                $insert->execute(['id' => Uuid::v4(), 'rental' => $rentalId, 'article' => (string) $articleId, 'extra' => $extra]);
            }
        }
        // Rows that only existed for extras and have nothing issued any more.
        $this->pdo->prepare(
            'DELETE ri FROM rental_items ri
             WHERE ri.rental_id = :id AND ri.quantity = 0 AND ri.tour_extra_quantity = 0
               AND NOT EXISTS (SELECT 1 FROM rental_issued_items x WHERE x.rental_id = ri.rental_id AND x.article_id = ri.article_id)'
        )->execute(['id' => $rentalId]);
    }

    public function minuteProgramsByStop(string $rentalId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, tour_stop_id FROM minute_programs WHERE rental_id = :id AND tour_stop_id IS NOT NULL ORDER BY start_at');
        $stmt->execute(['id' => $rentalId]);
        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[(string) $row['tour_stop_id']][] = ['id' => (string) $row['id'], 'name' => (string) $row['name']];
        }

        return $result;
    }

    public function assignMinuteProgram(string $minuteProgramId, ?string $stopId): void
    {
        $this->pdo->prepare('UPDATE minute_programs SET tour_stop_id = :stop WHERE id = :id')->execute(['stop' => $stopId, 'id' => $minuteProgramId]);
    }

    /** @param array<string, mixed> $r */
    private function hydrateStop(array $r): TourStop
    {
        $dt = static fn ($v): ?DateTimeImmutable => $v !== null ? new DateTimeImmutable((string) $v) : null;

        return new TourStop(
            id: (string) $r['id'],
            rentalId: (string) $r['rental_id'],
            kind: TourStopKind::tryFrom((string) $r['kind']) ?? TourStopKind::Stop,
            venueId: $r['venue_id'],
            locationText: $r['location_text'],
            setupAt: $dt($r['setup_at']),
            eventStartAt: $dt($r['event_start_at']),
            eventEndAt: $dt($r['event_end_at']),
            teardownEndAt: $dt($r['teardown_end_at']),
            contactName: $r['contact_name'],
            contactPhone: $r['contact_phone'],
            note: $r['note'],
            afterStopId: $r['after_stop_id'],
            purpose: $r['purpose'] !== null ? WaypointPurpose::tryFrom((string) $r['purpose']) : null,
            addressType: $r['address_type'] !== null ? WaypointAddressType::tryFrom((string) $r['address_type']) : null,
            warehouseId: $r['warehouse_id'],
            savedAddressId: $r['saved_address_id'],
            label: $r['label'],
            street: $r['street'],
            postalCode: $r['postal_code'],
            city: $r['city'],
            country: $r['country'],
            latitude: $r['latitude'] !== null ? (float) $r['latitude'] : null,
            longitude: $r['longitude'] !== null ? (float) $r['longitude'] : null,
            arriveAt: $dt($r['arrive_at']),
            departAt: $dt($r['depart_at']),
            createdAt: $dt($r['created_at']),
            arrivalNotBilled: (bool) ($r['arrival_not_billed'] ?? false),
            arrivalKmOverride: isset($r['arrival_km_override']) ? (int) $r['arrival_km_override'] : null,
        );
    }

    /** @param array<string, mixed> $r */
    private function hydrateItem(array $r): TourStopItem
    {
        return new TourStopItem(
            (string) $r['id'],
            (string) $r['stop_id'],
            (string) $r['article_id'],
            (int) $r['delta'],
            TourAllocation::tryFrom((string) $r['allocation']) ?? TourAllocation::StopsOnly,
            $r['issue_waypoint_id'],
            $r['return_waypoint_id'],
        );
    }

    /** @param array<string, mixed> $r */
    private function hydrateAddress(array $r): TourSavedAddress
    {
        return new TourSavedAddress(
            (string) $r['id'],
            (string) $r['label'],
            (string) $r['street'],
            (string) $r['postal_code'],
            (string) $r['city'],
            $r['country'],
            $r['latitude'] !== null ? (float) $r['latitude'] : null,
            $r['longitude'] !== null ? (float) $r['longitude'] : null,
        );
    }
}
