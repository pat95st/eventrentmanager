<?php

declare(strict_types=1);

namespace App\Domain\Network;

/**
 * Fixed address range for devices of a category (migration 155). Used as
 * the default network and suggestion range when assigning addresses; it
 * applies to subcategories without an own range and can be overridden.
 */
final class NetworkCategoryRange
{
    public function __construct(
        public readonly string $categoryId,
        public string $networkId,
        public ?string $ipv4Start = null,
        public ?string $ipv4End = null,
        public ?string $ipv6Start = null,
        public ?string $ipv6End = null,
    ) {
    }

    /** @return array{0: string, 1: string}|null packed start and end */
    public function range(int $version): ?array
    {
        $start = $version === 4 ? $this->ipv4Start : $this->ipv6Start;
        $end = $version === 4 ? $this->ipv4End : $this->ipv6End;
        if ($start === null || $end === null) {
            return null;
        }
        $startPacked = IpAddress::parse($start, $version);
        $endPacked = IpAddress::parse($end, $version);

        return $startPacked !== null && $endPacked !== null ? [$startPacked, $endPacked] : null;
    }

    public function label(int $version): ?string
    {
        $start = $version === 4 ? $this->ipv4Start : $this->ipv6Start;
        $end = $version === 4 ? $this->ipv4End : $this->ipv6End;

        return $start !== null && $end !== null ? $start . ' – ' . $end : null;
    }
}
