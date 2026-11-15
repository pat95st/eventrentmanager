<?php

declare(strict_types=1);

namespace App\Domain\Network;

/** Address families of a network (migration 155). */
enum IpMode: string
{
    case Ipv4 = 'ipv4';
    case Ipv6 = 'ipv6';
    case Dual = 'dual';

    public function label(): string
    {
        return match ($this) {
            self::Ipv4 => 'nur IPv4',
            self::Ipv6 => 'nur IPv6',
            self::Dual => 'Dualstack (IPv4 + IPv6)',
        };
    }

    public function supports(int $version): bool
    {
        return $this === self::Dual || ($version === 4 ? $this === self::Ipv4 : $this === self::Ipv6);
    }
}
