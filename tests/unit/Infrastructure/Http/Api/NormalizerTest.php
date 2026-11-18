<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Http\Api;

use App\Domain\Customer\CustomerType;
use App\Infrastructure\Http\Api\Normalizer;
use Codeception\Test\Unit;
use DateTimeImmutable;

final class NormalizerTest extends Unit
{
    public function testConvertsPublicPropertiesToSnakeCase(): void
    {
        $object = new class () {
            public string $firstName = 'Erika';
            public CustomerType $type = CustomerType::Privat;
            public DateTimeImmutable $createdAt;
            public DateTimeImmutable $startDate;
            public ?string $addressLine2 = null;
            public string $passwordHash = 'secret';
            private string $hidden = 'x';

            public function __construct()
            {
                $this->createdAt = new DateTimeImmutable('2026-11-17 10:00:00+01:00');
                $this->startDate = new DateTimeImmutable('2026-12-01');
            }
        };

        $this->assertSame([
            'first_name' => 'Erika',
            'type' => 'privat',
            'created_at' => '2026-11-17T10:00:00+01:00',
            'start_date' => '2026-12-01',
            'address_line2' => null,
        ], Normalizer::normalize($object));
    }

    public function testExcludesRequestedProperties(): void
    {
        $object = new class () {
            public string $name = 'A';
            public string $storedFilename = 'x.pdf';
        };

        $this->assertSame(['name' => 'A'], Normalizer::normalize($object, ['storedFilename']));
    }

    public function testNormalizesNestedArraysAndObjects(): void
    {
        $inner = new class () {
            public int $quantity = 2;
        };

        $this->assertSame(['items' => [['quantity' => 2]], 'nan' => null], Normalizer::normalize(['items' => [$inner], 'nan' => NAN]));
    }

    public function testCaseConversion(): void
    {
        $this->assertSame('daily_rental_price', Normalizer::snake('dailyRentalPrice'));
        $this->assertSame('already_snake', Normalizer::snake('already_snake'));
        $this->assertSame('dailyRentalPrice', Normalizer::camel('daily_rental_price'));
        $this->assertTrue(Normalizer::isDateField('acquisitionDate'));
        $this->assertTrue(Normalizer::isDateField('date'));
        $this->assertFalse(Normalizer::isDateField('updatedAt'));
    }
}
