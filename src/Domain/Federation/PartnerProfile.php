<?php

declare(strict_types=1);

namespace App\Domain\Federation;

/**
 * Company details a partner installation shares with us (name and address
 * from its company settings). Used to create the customer that represents
 * the partner when we accept its rental requests.
 */
final class PartnerProfile
{
    public function __construct(
        public readonly ?string $street = null,
        public readonly ?string $postalCode = null,
        public readonly ?string $city = null,
        public readonly ?string $country = null,
        public readonly ?string $email = null,
        public readonly ?string $phone = null,
        public readonly ?string $website = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $text = static function (string $key) use ($data): ?string {
            $value = $data[$key] ?? null;
            if (!is_scalar($value)) {
                return null;
            }
            $value = trim(mb_substr((string) $value, 0, 255));

            return $value === '' ? null : $value;
        };

        return new self($text('street'), $text('postal_code'), $text('city'), $text('country'), $text('email'), $text('phone'), $text('website'));
    }

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return [
            'street' => $this->street,
            'postal_code' => $this->postalCode,
            'city' => $this->city,
            'country' => $this->country,
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
        ];
    }

    public function addressLine(): string
    {
        return trim(implode(', ', array_filter([$this->street, trim(($this->postalCode ?? '') . ' ' . ($this->city ?? ''))], static fn ($v) => $v !== null && $v !== '')));
    }
}
