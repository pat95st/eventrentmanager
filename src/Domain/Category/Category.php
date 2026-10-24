<?php

declare(strict_types=1);

namespace App\Domain\Category;

use DateTimeImmutable;

final class Category
{
    public function __construct(
        public readonly string $id,
        public string $name,
        public ?string $parentId,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }
}
