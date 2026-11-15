<?php

declare(strict_types=1);

namespace App\Domain\Network;

/**
 * A network devices may get addresses from (migration 155, settings ›
 * networks): IPv4 and/or IPv6 subnet from the private ranges, optional VLAN.
 * Addresses are stored in canonical text form (see IpAddress::format()).
 */
final class Network
{
    public const IPV4_MIN_PREFIX = 8;
    public const IPV4_MAX_PREFIX = 30;
    public const IPV6_MIN_PREFIX = 7;
    public const IPV6_MAX_PREFIX = 127;

    public function __construct(
        public readonly string $id,
        public string $name,
        public IpMode $mode,
        public ?int $vlanId = null,
        public ?string $ipv4Network = null,
        public ?int $ipv4Prefix = null,
        public ?string $ipv6Network = null,
        public ?int $ipv6Prefix = null,
        public ?int $ipv6DefaultPrefix = null,
        public ?string $notes = null,
        public int $sortOrder = 0,
    ) {
    }

    public function supports(int $version): bool
    {
        return $this->mode->supports($version) && $this->subnet($version) !== null;
    }

    /** @return array{0: string, 1: int}|null packed network address and prefix length */
    public function subnet(int $version): ?array
    {
        $address = $version === 4 ? $this->ipv4Network : $this->ipv6Network;
        $prefix = $version === 4 ? $this->ipv4Prefix : $this->ipv6Prefix;
        if ($address === null || $prefix === null) {
            return null;
        }
        $packed = IpAddress::parse($address, $version);

        return $packed === null ? null : [$packed, $prefix];
    }

    /** CIDR notation, e.g. "192.168.10.0/24". */
    public function cidr(int $version): ?string
    {
        $address = $version === 4 ? $this->ipv4Network : $this->ipv6Network;
        $prefix = $version === 4 ? $this->ipv4Prefix : $this->ipv6Prefix;

        return $address !== null && $prefix !== null ? $address . '/' . $prefix : null;
    }

    /** Dotted IPv4 subnet mask, e.g. "255.255.255.0". */
    public function ipv4SubnetMask(): ?string
    {
        return $this->ipv4Prefix !== null ? IpAddress::prefixToSubnetMask($this->ipv4Prefix) : null;
    }

    /**
     * First and last address that may be assigned to a device: without the
     * network address and, for IPv4, without the broadcast address.
     *
     * @return array{0: string, 1: string}|null packed addresses
     */
    public function hostRange(int $version): ?array
    {
        $subnet = $this->subnet($version);
        if ($subnet === null) {
            return null;
        }
        [$packed, $prefix] = $subnet;
        $first = IpAddress::increment(IpAddress::networkAddress($packed, $prefix));
        $last = IpAddress::lastAddress($packed, $prefix);
        if ($version === 4) {
            $last = IpAddress::decrement($last);
        }
        if ($first === null || $last === null || IpAddress::compare($first, $last) > 0) {
            return null;
        }

        return [$first, $last];
    }

    /** Whether the address (canonical or not) may be assigned to a device in this network. */
    public function isAssignable(string $address, int $version): bool
    {
        $packed = IpAddress::parse($address, $version);
        $range = $this->hostRange($version);

        return $packed !== null && $range !== null
            && IpAddress::compare($packed, $range[0]) >= 0
            && IpAddress::compare($packed, $range[1]) <= 0;
    }

    /** Short description for lists, e.g. "192.168.10.0/24 · fd00:10::/64 · VLAN 10". */
    public function summary(): string
    {
        $parts = [];
        foreach ([4, 6] as $version) {
            if ($this->mode->supports($version) && ($cidr = $this->cidr($version)) !== null) {
                $parts[] = $cidr;
            }
        }
        if ($this->vlanId !== null) {
            $parts[] = 'VLAN ' . $this->vlanId;
        }

        return implode(' · ', $parts);
    }

    /** Address with prefix length for display, e.g. "192.168.10.5/24" or "fd00:10::5/64". */
    public function addressWithPrefix(string $address, int $version): string
    {
        $prefix = $version === 4 ? $this->ipv4Prefix : ($this->ipv6DefaultPrefix ?? $this->ipv6Prefix);

        return $prefix !== null ? $address . '/' . $prefix : $address;
    }
}
