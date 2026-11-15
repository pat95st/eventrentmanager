<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Network;

use App\Application\Network\IpAddressSuggester;
use Codeception\Test\Unit;

final class IpAddressSuggesterTest extends Unit
{
    private static function range(string $from, string $to): array
    {
        return [(string) inet_pton($from), (string) inet_pton($to)];
    }

    public function testStartsAtTheBeginningOfAnEmptyRange(): void
    {
        $this->assertSame(['192.168.10.100'], IpAddressSuggester::suggest(self::range('192.168.10.100', '192.168.10.199'), 4, []));
    }

    public function testSuggestsLastUsedAddressPlusOne(): void
    {
        $used = ['192.168.10.100', '192.168.10.105', '192.168.10.20', '192.168.10.102'];

        $this->assertSame(
            ['192.168.10.106', '192.168.10.107'],
            IpAddressSuggester::suggest(self::range('192.168.10.100', '192.168.10.199'), 4, $used, 2),
            'Addresses outside the range (.20) are ignored; gaps below the highest address are not used first.',
        );
    }

    public function testWrapsAroundToGapsWhenTheEndIsReached(): void
    {
        $used = ['10.0.0.1', '10.0.0.3', '10.0.0.4'];

        $this->assertSame(['10.0.0.2'], IpAddressSuggester::suggest(self::range('10.0.0.1', '10.0.0.4'), 4, $used, 3));
        $this->assertSame([], IpAddressSuggester::suggest(self::range('10.0.0.1', '10.0.0.2'), 4, ['10.0.0.1', '10.0.0.2']));
    }

    public function testWorksForIpv6(): void
    {
        $this->assertSame(
            ['fd00:10::10', 'fd00:10::11'],
            IpAddressSuggester::suggest(self::range('fd00:10::1', 'fd00:10::ffff'), 6, ['fd00:10::f'], 2),
        );
    }
}
