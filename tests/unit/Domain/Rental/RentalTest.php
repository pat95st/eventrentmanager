<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Rental;

use App\Domain\Rental\Rental;
use Codeception\Test\Unit;
use DateTimeImmutable;

final class RentalTest extends Unit
{
    public function testMaxDeploymentDaysCountsBothStartAndEndDateInclusively(): void
    {
        $rental = $this->makeRental(
            startDate: new DateTimeImmutable('2026-06-01'),
            endDate: new DateTimeImmutable('2026-06-01'),
        );

        // Ein Tag Start = Ende zählt als EIN Einsatztag, nicht null.
        $this->assertSame(1, $rental->maxDeploymentDays());
    }

    public function testMaxDeploymentDaysOverMultipleDays(): void
    {
        $rental = $this->makeRental(
            startDate: new DateTimeImmutable('2026-06-01'),
            endDate: new DateTimeImmutable('2026-06-05'),
        );

        // 1., 2., 3., 4., 5. Juni = 5 Einsatztage (inklusive Endtag).
        $this->assertSame(5, $rental->maxDeploymentDays());
    }

    private function makeRental(DateTimeImmutable $startDate, DateTimeImmutable $endDate): Rental
    {
        $now = new DateTimeImmutable();

        return new Rental(
            id: 'rental-1',
            customerId: 'customer-1',
            startDate: $startDate,
            endDate: $endDate,
            eventName: 'Test-Veranstaltung',
            eventLocation: null,
            venueId: null,
            deploymentDays: 1,
            createdAt: $now,
            updatedAt: $now,
        );
    }
}
