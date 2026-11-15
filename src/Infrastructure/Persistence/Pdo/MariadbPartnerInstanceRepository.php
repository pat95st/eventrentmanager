<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Federation\DiscountProposalSource;
use App\Domain\Federation\PartnerInstance;
use App\Domain\Federation\PartnerInstanceRepositoryInterface;
use App\Domain\Federation\PartnerProfile;
use App\Domain\Federation\PartnerStatus;
use DateTimeImmutable;
use PDO;

final class MariadbPartnerInstanceRepository implements PartnerInstanceRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(PartnerInstance $partner): void
    {
        $this->pdo->prepare(
            'INSERT INTO partner_instances (id, name, base_url, status, outbound_token, inbound_token_hash, profile,
                auto_accept_requests, allow_technician_requests, discount_granted, discount_received,
                proposed_discount_granted, proposed_discount_received, proposal_from, customer_id, last_error,
                last_contact_at, confirmed_at, created_at, updated_at)
             VALUES (:id, :name, :base_url, :status, :outbound_token, :inbound_token_hash, :profile,
                :auto_accept_requests, :allow_technician_requests, :discount_granted, :discount_received,
                :proposed_discount_granted, :proposed_discount_received, :proposal_from, :customer_id, :last_error,
                :last_contact_at, :confirmed_at, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE name = VALUES(name), base_url = VALUES(base_url), status = VALUES(status),
                outbound_token = VALUES(outbound_token), inbound_token_hash = VALUES(inbound_token_hash),
                profile = VALUES(profile), auto_accept_requests = VALUES(auto_accept_requests),
                allow_technician_requests = VALUES(allow_technician_requests),
                discount_granted = VALUES(discount_granted), discount_received = VALUES(discount_received),
                proposed_discount_granted = VALUES(proposed_discount_granted),
                proposed_discount_received = VALUES(proposed_discount_received),
                proposal_from = VALUES(proposal_from), customer_id = VALUES(customer_id),
                last_error = VALUES(last_error), last_contact_at = VALUES(last_contact_at),
                confirmed_at = VALUES(confirmed_at), updated_at = VALUES(updated_at)',
        )->execute([
            'id' => $partner->id,
            'name' => $partner->name,
            'base_url' => $partner->baseUrl,
            'status' => $partner->status->value,
            'outbound_token' => $partner->outboundToken,
            'inbound_token_hash' => $partner->inboundTokenHash,
            'profile' => json_encode($partner->profile->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'auto_accept_requests' => $partner->autoAcceptRequests ? 1 : 0,
            'allow_technician_requests' => $partner->allowTechnicianRequests ? 1 : 0,
            'discount_granted' => number_format($partner->discountGranted, 2, '.', ''),
            'discount_received' => number_format($partner->discountReceived, 2, '.', ''),
            'proposed_discount_granted' => $partner->proposedDiscountGranted !== null ? number_format($partner->proposedDiscountGranted, 2, '.', '') : null,
            'proposed_discount_received' => $partner->proposedDiscountReceived !== null ? number_format($partner->proposedDiscountReceived, 2, '.', '') : null,
            'proposal_from' => $partner->proposalFrom?->value,
            'customer_id' => $partner->customerId,
            'last_error' => $partner->lastError,
            'last_contact_at' => $partner->lastContactAt?->format('Y-m-d H:i:s'),
            'confirmed_at' => $partner->confirmedAt?->format('Y-m-d H:i:s'),
            'created_at' => $partner->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $partner->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?PartnerInstance
    {
        return $this->findOneBy('id', $id);
    }

    public function findByInboundTokenHash(string $hash): ?PartnerInstance
    {
        return $this->findOneBy('inbound_token_hash', $hash);
    }

    public function findAll(): array
    {
        return array_map(fn (array $row) => $this->hydrate($row), $this->pdo->query('SELECT * FROM partner_instances ORDER BY name')->fetchAll(PDO::FETCH_ASSOC));
    }

    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM partner_instances WHERE id = :id')->execute(['id' => $id]);
    }

    private function findOneBy(string $column, string $value): ?PartnerInstance
    {
        $statement = $this->pdo->prepare("SELECT * FROM partner_instances WHERE {$column} = :value LIMIT 1");
        $statement->execute(['value' => $value]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): PartnerInstance
    {
        $profile = json_decode((string) ($row['profile'] ?? ''), true);
        $date = static fn ($value) => $value !== null ? new DateTimeImmutable((string) $value) : null;

        return new PartnerInstance(
            id: (string) $row['id'],
            name: (string) $row['name'],
            baseUrl: (string) $row['base_url'],
            status: PartnerStatus::tryFrom((string) $row['status']) ?? PartnerStatus::Ended,
            outboundToken: $row['outbound_token'] !== null ? (string) $row['outbound_token'] : null,
            inboundTokenHash: $row['inbound_token_hash'] !== null ? (string) $row['inbound_token_hash'] : null,
            profile: PartnerProfile::fromArray(is_array($profile) ? $profile : []),
            autoAcceptRequests: (bool) $row['auto_accept_requests'],
            allowTechnicianRequests: (bool) $row['allow_technician_requests'],
            discountGranted: (float) $row['discount_granted'],
            discountReceived: (float) $row['discount_received'],
            proposedDiscountGranted: $row['proposed_discount_granted'] !== null ? (float) $row['proposed_discount_granted'] : null,
            proposedDiscountReceived: $row['proposed_discount_received'] !== null ? (float) $row['proposed_discount_received'] : null,
            proposalFrom: DiscountProposalSource::tryFrom((string) ($row['proposal_from'] ?? '')),
            customerId: $row['customer_id'] !== null ? (string) $row['customer_id'] : null,
            lastError: $row['last_error'] !== null ? (string) $row['last_error'] : null,
            lastContactAt: $date($row['last_contact_at']),
            confirmedAt: $date($row['confirmed_at']),
            createdAt: new DateTimeImmutable((string) $row['created_at']),
            updatedAt: new DateTimeImmutable((string) $row['updated_at']),
        );
    }
}
