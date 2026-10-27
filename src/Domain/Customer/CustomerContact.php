<?php

declare(strict_types=1);

namespace App\Domain\Customer;

/**
 * Ein Ansprechpartner eines Kunden (siehe Migration 100) — ein Kunde kann
 * mehrere davon haben, insbesondere bei Firmenkunden, bei denen die
 * zuständige Person öfter wechselt als der Kunde selbst.
 */
final class CustomerContact
{
    public function __construct(
        public readonly string $id,
        public readonly string $customerId,
        public string $name,
        public ?string $jobTitle,
        public ?string $phone,
        public ?string $email,
        public int $sortOrder,
    ) {
    }
}
