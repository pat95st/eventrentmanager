<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Network;

use App\Domain\Network\IpAddress;
use App\Domain\Network\IpMode;
use App\Domain\Network\MacAddress;
use App\Domain\Network\Network;
use Codeception\Test\Unit;

final class IpAddressTest extends Unit
{
    public function testParsesPrefixInCidrAndDottedNotation(): void
    {
        $this->assertSame(24, IpAddress::parsePrefix('24', 4));
        $this->assertSame(24, IpAddress::parsePrefix('/24', 4));
        $this->assertSame(24, IpAddress::parsePrefix('255.255.255.0', 4));
        $this->assertSame(20, IpAddress::parsePrefix('255.255.240.0', 4));
        $this->assertNull(IpAddress::parsePrefix('255.0.255.0', 4), 'Non-contiguous masks are invalid.');
        $this->assertNull(IpAddress::parsePrefix('33', 4));
        $this->assertSame(64, IpAddress::parsePrefix('/64', 6));
        $this->assertNull(IpAddress::parsePrefix('ffff:ffff::', 6));
        $this->assertSame('255.255.255.192', IpAddress::prefixToSubnetMask(26));
    }

    public function testOnlyPrivateNetworksArePrivate(): void
    {
        $private = static fn (string $address, int $prefix) => IpAddress::isPrivateNetwork((string) inet_pton($address), $prefix);

        $this->assertTrue($private('10.20.0.0', 16));
        $this->assertTrue($private('172.16.0.0', 12));
        $this->assertTrue($private('172.31.255.0', 24));
        $this->assertTrue($private('192.168.10.0', 24));
        $this->assertFalse($private('172.32.0.0', 16));
        $this->assertFalse($private('8.8.8.0', 24));
        $this->assertFalse($private('192.168.0.0', 15), 'Wider than the private range.');
        $this->assertTrue($private('fd00:10::', 64));
        $this->assertFalse($private('2001:db8::', 32));
    }

    public function testIncrementAndDecrementCarryOver(): void
    {
        $this->assertSame('192.168.1.0', IpAddress::format((string) IpAddress::increment((string) inet_pton('192.168.0.255'))));
        $this->assertSame('fd00::1:0', IpAddress::format((string) IpAddress::increment((string) inet_pton('fd00::ffff'))));
        $this->assertSame('10.0.0.255', IpAddress::format((string) IpAddress::decrement((string) inet_pton('10.0.1.0'))));
        $this->assertNull(IpAddress::increment((string) inet_pton('255.255.255.255')));
    }

    public function testHostRangeExcludesNetworkAndBroadcast(): void
    {
        $network = new Network('n', 'LAN', IpMode::Dual, null, '192.168.10.0', 24, 'fd00:10::', 64, 64);

        $range = $network->hostRange(4);
        $this->assertSame(['192.168.10.1', '192.168.10.254'], [IpAddress::format($range[0]), IpAddress::format($range[1])]);
        $this->assertTrue($network->isAssignable('192.168.10.254', 4));
        $this->assertFalse($network->isAssignable('192.168.10.255', 4));
        $this->assertFalse($network->isAssignable('192.168.10.0', 4));
        $this->assertFalse($network->isAssignable('192.168.11.1', 4));
        $this->assertTrue($network->isAssignable('FD00:10::ffff', 6));
        $this->assertFalse($network->isAssignable('fd00:11::1', 6));
        $this->assertSame('255.255.255.0', $network->ipv4SubnetMask());
        $this->assertSame('192.168.10.0/24 · fd00:10::/64', $network->summary());
    }

    public function testNormalizesMacAddresses(): void
    {
        $this->assertSame('00:1D:C1:12:34:AB', MacAddress::normalize('00-1d-c1-12-34-ab'));
        $this->assertSame('00:1D:C1:12:34:AB', MacAddress::normalize('001d.c112.34ab'));
        $this->assertSame('00:1D:C1:12:34:AB', MacAddress::normalize('001DC11234AB'));
        $this->assertNull(MacAddress::normalize('00:1D:C1:12:34'));
        $this->assertNull(MacAddress::normalize('00:1D:C1:12:34:ZZ'));
    }
}
