<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Federation;

use App\Domain\Exception\ValidationException;
use App\Domain\Federation\Discount;
use App\Domain\Federation\PartnerInvitationLink;
use Codeception\Test\Unit;

final class PartnerInvitationLinkTest extends Unit
{
    public function testBuildAndParse(): void
    {
        $code = str_repeat('aB3-_', 9);
        $link = PartnerInvitationLink::build('https://Verleih.Example.de/app/', $code);
        self::assertSame('https://verleih.example.de/app/api/federation/v1/invitations/' . $code, $link);
        self::assertSame(['baseUrl' => 'https://verleih.example.de/app', 'code' => $code], PartnerInvitationLink::parse('  ' . $link . '/ '));
    }

    /** @dataProvider invalidLinks */
    public function testRejectsInvalidLinks(string $link): void
    {
        $this->expectException(ValidationException::class);
        PartnerInvitationLink::parse($link);
    }

    /** @return array<string, array{string}> */
    public static function invalidLinks(): array
    {
        return [
            'no path' => ['https://verleih.example.de'],
            'short code' => ['https://verleih.example.de/api/federation/v1/invitations/abc'],
            'ftp' => ['ftp://verleih.example.de/api/federation/v1/invitations/' . str_repeat('a', 40)],
            'credentials' => ['https://user:pw@verleih.example.de/api/federation/v1/invitations/' . str_repeat('a', 40)],
        ];
    }

    public function testDiscountParsing(): void
    {
        self::assertSame(12.5, Discount::parse('12,5', 'd'));
        self::assertSame(0.0, Discount::parse('', 'd'));
        self::assertSame(36.0, Discount::apply(40.0, 10.0));
        self::assertSame('12,5 %', Discount::format(12.5));
        self::assertSame('10 %', Discount::format(10.0));
        $this->expectException(ValidationException::class);
        Discount::parse('101', 'd');
    }
}
