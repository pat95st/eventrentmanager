<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Voucher\Voucher;
use App\Domain\Voucher\VoucherDiscountType;
use App\Domain\Voucher\VoucherRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbVoucherRepository implements VoucherRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(Voucher $voucher): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO vouchers (
                id, code, discount_type, discount_value, valid_from, valid_until, notes, created_at, updated_at
             ) VALUES (
                :id, :code, :discount_type, :discount_value, :valid_from, :valid_until, :notes, :created_at, :updated_at
             )
             ON DUPLICATE KEY UPDATE
                discount_type = :discount_type2, discount_value = :discount_value2, valid_from = :valid_from2,
                valid_until = :valid_until2, notes = :notes2, updated_at = :updated_at2'
        );

        $stmt->execute([
            'id' => $voucher->id,
            'code' => $voucher->code,
            'discount_type' => $voucher->discountType->value,
            'discount_value' => $voucher->discountValue,
            'valid_from' => $voucher->validFrom->format('Y-m-d'),
            'valid_until' => $voucher->validUntil->format('Y-m-d'),
            'notes' => $voucher->notes,
            'created_at' => $voucher->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $voucher->updatedAt->format('Y-m-d H:i:s'),
            'discount_type2' => $voucher->discountType->value,
            'discount_value2' => $voucher->discountValue,
            'valid_from2' => $voucher->validFrom->format('Y-m-d'),
            'valid_until2' => $voucher->validUntil->format('Y-m-d'),
            'notes2' => $voucher->notes,
            'updated_at2' => $voucher->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?Voucher
    {
        $stmt = $this->pdo->prepare('SELECT * FROM vouchers WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByCode(string $code): ?Voucher
    {
        $stmt = $this->pdo->prepare('SELECT * FROM vouchers WHERE code = :code');
        $stmt->execute(['code' => $code]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(?string $search = null, ?int $limit = null, ?int $offset = null): array
    {
        [$where, $params] = $this->buildSearchWhere($search);

        $sql = 'SELECT * FROM vouchers' . $where . ' ORDER BY created_at DESC';
        if ($limit !== null) {
            $sql .= ' LIMIT :limit OFFSET :offset';
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        if ($limit !== null) {
            $stmt->bindValue('limit', max(0, $limit), PDO::PARAM_INT);
            $stmt->bindValue('offset', max(0, $offset ?? 0), PDO::PARAM_INT);
        }
        $stmt->execute();

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function countAll(?string $search = null): int
    {
        [$where, $params] = $this->buildSearchWhere($search);

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM vouchers' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /** @return array{0: string, 1: array<string, string>} */
    private function buildSearchWhere(?string $search): array
    {
        if ($search === null || trim($search) === '') {
            return ['', []];
        }

        return [' WHERE code LIKE :search', ['search' => '%' . $search . '%']];
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM vouchers WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function hydrate(array $row): Voucher
    {
        return new Voucher(
            id: $row['id'],
            code: $row['code'],
            discountType: VoucherDiscountType::from($row['discount_type']),
            discountValue: $row['discount_value'],
            validFrom: new DateTimeImmutable($row['valid_from']),
            validUntil: new DateTimeImmutable($row['valid_until']),
            notes: $row['notes'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }
}
