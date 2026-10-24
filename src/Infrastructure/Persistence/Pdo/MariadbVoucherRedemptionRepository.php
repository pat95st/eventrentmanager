<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Voucher\VoucherRedemption;
use App\Domain\Voucher\VoucherRedemptionRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbVoucherRedemptionRepository implements VoucherRedemptionRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(VoucherRedemption $redemption): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO voucher_redemptions (id, voucher_id, rental_id, redeemed_at)
             VALUES (:id, :voucher_id, :rental_id, :redeemed_at)'
        );

        $stmt->execute([
            'id' => $redemption->id,
            'voucher_id' => $redemption->voucherId,
            'rental_id' => $redemption->rentalId,
            'redeemed_at' => $redemption->redeemedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findByVoucherId(string $voucherId): ?VoucherRedemption
    {
        $stmt = $this->pdo->prepare('SELECT * FROM voucher_redemptions WHERE voucher_id = :voucher_id');
        $stmt->execute(['voucher_id' => $voucherId]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByRentalId(string $rentalId): ?VoucherRedemption
    {
        $stmt = $this->pdo->prepare('SELECT * FROM voucher_redemptions WHERE rental_id = :rental_id');
        $stmt->execute(['rental_id' => $rentalId]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByVoucherIds(array $voucherIds): array
    {
        if ($voucherIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($voucherIds), '?'));
        $stmt = $this->pdo->prepare("SELECT * FROM voucher_redemptions WHERE voucher_id IN ({$placeholders})");
        $stmt->execute(array_values($voucherIds));

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $redemption = $this->hydrate($row);
            $result[$redemption->voucherId] = $redemption;
        }

        return $result;
    }

    public function deleteByVoucherId(string $voucherId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM voucher_redemptions WHERE voucher_id = :voucher_id');
        $stmt->execute(['voucher_id' => $voucherId]);
    }

    private function hydrate(array $row): VoucherRedemption
    {
        return new VoucherRedemption(
            id: $row['id'],
            voucherId: $row['voucher_id'],
            rentalId: $row['rental_id'],
            redeemedAt: new DateTimeImmutable($row['redeemed_at']),
        );
    }
}
