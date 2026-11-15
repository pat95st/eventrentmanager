<?php

declare(strict_types=1);

namespace App\Domain\Federation;

/** One requested article of a material request (article of the providing installation). */
final class PartnerRequestItem
{
    public function __construct(
        public readonly string $articleId,
        public readonly string $name,
        public readonly int $quantity,
        public readonly ?float $dailyPrice = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): ?self
    {
        $articleId = is_scalar($data['article_id'] ?? null) ? trim((string) $data['article_id']) : '';
        $quantity = filter_var($data['quantity'] ?? null, FILTER_VALIDATE_INT);
        if ($articleId === '' || $quantity === false || $quantity < 1) {
            return null;
        }
        $name = is_scalar($data['name'] ?? null) ? mb_substr(trim((string) $data['name']), 0, 255) : '';
        $price = $data['daily_price'] ?? null;

        return new self(mb_substr($articleId, 0, 36), $name, min($quantity, 100000), is_numeric($price) ? (float) $price : null);
    }

    /** @return array{article_id: string, name: string, quantity: int, daily_price: float|null} */
    public function toArray(): array
    {
        return ['article_id' => $this->articleId, 'name' => $this->name, 'quantity' => $this->quantity, 'daily_price' => $this->dailyPrice];
    }
}
