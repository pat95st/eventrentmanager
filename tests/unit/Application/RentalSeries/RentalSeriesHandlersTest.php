<?php

declare(strict_types=1);

namespace Tests\Unit\Application\RentalSeries;

use App\Application\RentalSeries\Command\ChangeRentalSeriesRule;
use App\Application\RentalSeries\Command\ChangeRentalSeriesRuleHandler;
use App\Application\RentalSeries\Command\CopyRentalData;
use App\Application\RentalSeries\Command\CopyRentalDataHandler;
use App\Application\RentalSeries\Command\CreateRentalSeries;
use App\Application\RentalSeries\Command\CreateRentalSeriesHandler;
use App\Application\RentalSeries\Command\DetachRentalFromSeries;
use App\Application\RentalSeries\Command\DetachRentalFromSeriesHandler;
use App\Application\RentalSeries\Command\EndRentalSeries;
use App\Application\RentalSeries\Command\EndRentalSeriesHandler;
use App\Application\RentalSeries\Command\ExtendRentalSeries;
use App\Application\RentalSeries\Command\ExtendRentalSeriesHandler;
use App\Application\RentalSeries\OccurrenceChecker;
use App\Application\RentalSeries\Query\GetRentalSeriesContext;
use App\Application\RentalSeries\Query\GetRentalSeriesContextHandler;
use App\Application\RentalSeries\Query\PreviewRentalSeries;
use App\Application\RentalSeries\Query\PreviewRentalSeriesHandler;
use App\Application\RentalSeries\RentalCopier;
use App\Application\RentalSeries\SeriesSupport;
use App\Domain\Exception\ValidationException;
use App\Domain\Rental\Rental;
use App\Domain\Rental\RentalIssuedItem;
use App\Domain\RentalSeries\CopyScope;
use App\Domain\RentalSeries\RecurrenceCalculator;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Tests\Support\Fakes\FakeRentalChildDataCopier;
use Tests\Support\Fakes\InMemoryRentalIssuedItemRepository;
use Tests\Support\Fakes\InMemoryRentalRepository;
use Tests\Support\Fakes\InMemoryRentalSeriesRepository;
use Tests\Support\Fakes\InMemoryRentalTechnicianRepository;
use Tests\Support\Fakes\InMemoryVenueRepository;

final class RentalSeriesHandlersTest extends Unit
{
    private InMemoryRentalRepository $rentals;
    private InMemoryRentalSeriesRepository $seriesRepository;
    private InMemoryRentalIssuedItemRepository $issued;
    private FakeRentalChildDataCopier $childData;
    private SeriesSupport $support;
    private RentalCopier $copier;
    private OccurrenceChecker $checker;
    private RecurrenceCalculator $calculator;

    protected function _before(): void
    {
        $this->rentals = new InMemoryRentalRepository();
        $this->seriesRepository = new InMemoryRentalSeriesRepository();
        $this->issued = new InMemoryRentalIssuedItemRepository();
        $this->childData = new FakeRentalChildDataCopier();
        $this->support = new SeriesSupport($this->seriesRepository, $this->rentals);
        $this->copier = new RentalCopier($this->rentals, $this->issued, $this->childData, new InMemoryRentalTechnicianRepository(), new InMemoryVenueRepository());
        $this->checker = new OccurrenceChecker();
        $this->calculator = new RecurrenceCalculator();
    }

    private function source(): Rental
    {
        $now = new DateTimeImmutable();
        $rental = new Rental(
            id: 'source',
            customerId: 'customer-1',
            startDate: new DateTimeImmutable('2027-01-02'), // Saturday
            endDate: new DateTimeImmutable('2027-01-03'),
            eventName: 'Kneipenquiz',
            eventLocation: 'Stadthalle',
            venueId: null,
            deploymentDays: 2,
            createdAt: $now,
            updatedAt: $now,
            pickupWarehouseId: 'wh-1',
            pickupAt: new DateTimeImmutable('2027-01-01 14:00'),
            returnWarehouseId: 'wh-1',
            returnAt: new DateTimeImmutable('2027-01-04 10:00'),
            transportNote: 'Transporter nehmen',
        );
        $this->rentals->save($rental);
        $this->childData->material['source'] = ['article-1' => 4];

        return $rental;
    }

    /** @return array<string, string> */
    private static function weeklyRule(int $count = 4): array
    {
        return ['type' => 'weekly', 'interval' => '2', 'end_mode' => 'count', 'occurrence_count' => (string) $count];
    }

    /** @param array<string, string> $rule @return list<array<string, mixed>> */
    private function rowsFromPreview(array $rule): array
    {
        $preview = (new PreviewRentalSeriesHandler($this->support, $this->calculator, $this->checker))->handle(new PreviewRentalSeries('source', $rule));
        $rows = [];
        foreach ($preview->rows as $row) {
            $rows[] = [
                'start' => $row->startDate->format('Y-m-d'),
                'end' => $row->endDate->format('Y-m-d'),
                'location' => $row->location,
                'include' => true,
                'default_start' => $row->startDate->format('Y-m-d'),
                'default_end' => $row->endDate->format('Y-m-d'),
            ];
        }

        return $rows;
    }

    private function createSeries(array $rule, ?array $rows = null, string $template = '', ?array $scopes = null): string
    {
        $handler = new CreateRentalSeriesHandler($this->support, $this->seriesRepository, $this->rentals, $this->copier, $this->checker);

        return $handler->handle(new CreateRentalSeries(
            'source',
            $rule,
            $template,
            $scopes ?? array_map(static fn (CopyScope $s) => $s->value, CopyScope::defaults()),
            $rows ?? $this->rowsFromPreview($rule),
        ));
    }

    private function issue(string $rentalId): void
    {
        $this->issued->save(new RentalIssuedItem('issued-' . $rentalId, $rentalId, 'article-1', 'inv-1', new DateTimeImmutable()));
    }

    public function testPreviewProposesDatesWithDurationKept(): void
    {
        $this->source();
        $preview = (new PreviewRentalSeriesHandler($this->support, $this->calculator, $this->checker))->handle(new PreviewRentalSeries('source', self::weeklyRule()));

        $this->assertCount(3, $preview->rows);
        $this->assertSame('2027-01-16', $preview->rows[0]->startDate->format('Y-m-d'));
        $this->assertSame('2027-01-17', $preview->rows[0]->endDate->format('Y-m-d'));
        $this->assertSame('Stadthalle', $preview->rows[0]->location);
    }

    public function testCreateSeriesMakesSourceFirstOccurrenceAndCopiesData(): void
    {
        $this->source();
        $seriesId = $this->createSeries(self::weeklyRule());

        $occurrences = $this->rentals->findBySeries($seriesId);
        $this->assertCount(4, $occurrences);
        $this->assertSame('source', $occurrences[0]->id);
        $this->assertSame([1, 2, 3, 4], array_map(static fn (Rental $r) => $r->seriesIndex, $occurrences));

        $second = $occurrences[1];
        $this->assertSame('2027-01-16', $second->startDate->format('Y-m-d'));
        $this->assertSame('2027-01-17', $second->endDate->format('Y-m-d'));
        $this->assertSame('customer-1', $second->customerId);
        $this->assertSame('Stadthalle', $second->eventLocation);
        // Logistics relative to the new date: one day before at 14:00, one day after at 10:00.
        $this->assertSame('2027-01-15 14:00', $second->pickupAt?->format('Y-m-d H:i'));
        $this->assertSame('2027-01-18 10:00', $second->returnAt?->format('Y-m-d H:i'));
        $this->assertSame(['article-1' => 4], $this->childData->material[$second->id]);
        $this->assertSame(['material', 'minute_programs', 'power_plan', 'trades'], $this->childData->kindsFor($second->id));
        $this->assertFalse($second->seriesCustomized);
    }

    public function testRowsCanBeSkippedMovedAndGetOtherLocation(): void
    {
        $this->source();
        $rows = $this->rowsFromPreview(self::weeklyRule());
        $rows[0]['include'] = false;
        $rows[1]['start'] = '2027-01-31';
        $rows[1]['end'] = '2027-01-31';
        $rows[2]['location'] = 'Festzelt am See';

        $occurrences = $this->rentals->findBySeries($this->createSeries(self::weeklyRule(), $rows));

        $this->assertCount(3, $occurrences);
        $this->assertSame('2027-01-31', $occurrences[1]->startDate->format('Y-m-d'));
        $this->assertTrue($occurrences[1]->seriesCustomized);
        $this->assertSame('Festzelt am See', $occurrences[2]->eventLocation);
        $this->assertFalse($occurrences[2]->seriesCustomized);
    }

    public function testNameTemplateIsApplied(): void
    {
        $this->source();
        $occurrences = $this->rentals->findBySeries($this->createSeries(self::weeklyRule(3), null, 'Quiz #{nr}'));

        $this->assertSame(['Quiz #1', 'Quiz #2', 'Quiz #3'], array_map(static fn (Rental $r) => $r->eventName, $occurrences));
    }

    public function testOnlySelectedScopesAreCopied(): void
    {
        $this->source();
        $occurrences = $this->rentals->findBySeries($this->createSeries(self::weeklyRule(2), null, '', ['material']));

        $this->assertSame(['material'], $this->childData->kindsFor($occurrences[1]->id));
        $this->assertNull($occurrences[1]->eventLocation);
        $this->assertNull($occurrences[1]->pickupAt);
    }

    public function testSourceAlreadyInSeriesIsRejected(): void
    {
        $this->source();
        $this->createSeries(self::weeklyRule(2));

        $this->expectException(ValidationException::class);
        $this->createSeries(self::weeklyRule(2));
    }

    public function testRowBeforeSourceIsRejected(): void
    {
        $this->source();
        $rows = $this->rowsFromPreview(self::weeklyRule(2));
        $rows[0]['start'] = '2026-12-01';

        $this->expectException(ValidationException::class);
        $this->createSeries(self::weeklyRule(2), $rows);
    }

    public function testExtendCopiesFromPreviousOccurrence(): void
    {
        $this->source();
        $seriesId = $this->createSeries(self::weeklyRule(2));
        $occurrences = $this->rentals->findBySeries($seriesId);
        // The last occurrence got more material than the first.
        $this->childData->material[$occurrences[1]->id] = ['article-1' => 6];

        $handler = new ExtendRentalSeriesHandler($this->support, $this->seriesRepository, $this->calculator, $this->copier, $this->checker);
        $result = $handler->handle(new ExtendRentalSeries($seriesId, 'count', '', '2', '', ['material']));

        $this->assertSame(2, $result['created']);
        $occurrences = $this->rentals->findBySeries($seriesId);
        $this->assertSame(['2027-01-02', '2027-01-16', '2027-01-30', '2027-02-13'], array_map(static fn (Rental $r) => $r->startDate->format('Y-m-d'), $occurrences));
        $this->assertSame(['article-1' => 6], $this->childData->material[$occurrences[2]->id]);
        $this->assertSame(['article-1' => 6], $this->childData->material[$occurrences[3]->id]);
        $this->assertSame(4, $occurrences[3]->seriesIndex);
        $this->assertSame('2027-02-13', $this->seriesRepository->findById($seriesId)?->rule->endsOn?->format('Y-m-d'));
    }

    public function testCopyFromPreviousReplacesSelectedAreasAndSkipsStartedTargets(): void
    {
        $this->source();
        $seriesId = $this->createSeries(self::weeklyRule(4), null, '', ['material']);
        [$first, $second, $third, $fourth] = $this->rentals->findBySeries($seriesId);
        $this->childData->material[$second->id] = ['article-2' => 1];
        $this->issue($fourth->id);

        $handler = new CopyRentalDataHandler($this->support, $this->copier);
        $result = $handler->handle(new CopyRentalData($second->id, [$third->id, $fourth->id], ['material', 'location']));

        $this->assertCount(1, $result['copied']);
        $this->assertCount(1, $result['skipped']);
        $this->assertSame(['article-2' => 1], $this->childData->material[$third->id]);
        $this->assertSame(['article-1' => 4], $this->childData->material[$fourth->id]);
        unset($first);
    }

    public function testCopyBetweenDifferentSeriesIsRejected(): void
    {
        $this->source();
        $seriesId = $this->createSeries(self::weeklyRule(2));
        $now = new DateTimeImmutable();
        $this->rentals->save(new Rental('other', 'customer-1', $now, $now, 'Andere', null, null, 1, $now, $now));

        $this->expectException(ValidationException::class);
        (new CopyRentalDataHandler($this->support, $this->copier))->handle(new CopyRentalData($this->rentals->findBySeries($seriesId)[1]->id, ['other'], ['material']));
    }

    public function testChangeRuleMovesFreeOccurrencesAndKeepsStartedAndCustomized(): void
    {
        $this->source();
        $seriesId = $this->createSeries(self::weeklyRule(5));
        // 02.01., 16.01., 30.01., 13.02., 27.02.
        [$first, $second, $third, $fourth, $fifth] = $this->rentals->findBySeries($seriesId);
        $this->issue($second->id);
        $third->seriesCustomized = true;
        $this->rentals->save($third);

        $handler = new ChangeRentalSeriesRuleHandler($this->support, $this->seriesRepository, $this->rentals, $this->calculator, $this->copier, new DateTimeImmutable('2027-01-05'));
        // From 09.01. on: every Saturday, 4 occurrences (09., 16., 23., 30.01.)
        $result = $handler->handle(new ChangeRentalSeriesRule($seriesId, '2027-01-09', ['type' => 'weekly', 'interval' => '1', 'end_mode' => 'count', 'occurrence_count' => '4']));

        // 16.01. (started) and 30.01. (customized) stay; the free occurrences 13.02./27.02. move to 09.01./23.01.
        $this->assertSame(2, $result['kept']);
        $dates = array_map(static fn (Rental $r) => $r->startDate->format('Y-m-d'), $this->rentals->findBySeries($seriesId));
        $this->assertSame(['2027-01-02', '2027-01-09', '2027-01-16', '2027-01-23', '2027-01-30'], $dates);
        $this->assertSame(2, $result['moved']);
        $this->assertSame(0, $result['created']);
        $this->assertSame(0, $result['deleted']);
        // Moved occurrence keeps its id (and therefore its data), minute programs moved along.
        $this->assertSame('2027-01-09', $this->rentals->findById($fourth->id)?->startDate->format('Y-m-d'));
        $this->assertContains('shift_minute_programs', $this->childData->kindsFor($fourth->id));
        $this->assertSame([1, 2, 3, 4, 5], array_map(static fn (Rental $r) => $r->seriesIndex, $this->rentals->findBySeries($seriesId)));
        unset($first, $fifth);
    }

    public function testChangeRuleCreatesMissingAndDeletesSurplusOccurrences(): void
    {
        $this->source();
        $seriesId = $this->createSeries(self::weeklyRule(3)); // 02.01., 16.01., 30.01.
        $handler = new ChangeRentalSeriesRuleHandler($this->support, $this->seriesRepository, $this->rentals, $this->calculator, $this->copier, new DateTimeImmutable('2027-01-05'));

        $result = $handler->handle(new ChangeRentalSeriesRule($seriesId, '2027-01-09', ['type' => 'weekly', 'interval' => '1', 'end_mode' => 'count', 'occurrence_count' => '4']));
        $this->assertSame(['moved' => 2, 'created' => 2, 'deleted' => 0, 'kept' => 0], $result);
        $this->assertCount(5, $this->rentals->findBySeries($seriesId));

        $result = $handler->handle(new ChangeRentalSeriesRule($seriesId, '2027-01-09', ['type' => 'monthly_day', 'interval' => '1', 'end_mode' => 'count', 'occurrence_count' => '2']));
        $this->assertSame(2, $result['deleted']);
        $dates = array_map(static fn (Rental $r) => $r->startDate->format('Y-m-d'), $this->rentals->findBySeries($seriesId));
        $this->assertSame(['2027-01-02', '2027-01-09', '2027-02-09'], $dates);
    }

    public function testEndSeriesDeletesOnlyFutureNotStartedOccurrences(): void
    {
        $this->source();
        $seriesId = $this->createSeries(self::weeklyRule(4)); // 02.01., 16.01., 30.01., 13.02.
        [, , $third, $fourth] = $this->rentals->findBySeries($seriesId);
        $this->issue($third->id);

        $handler = new EndRentalSeriesHandler($this->support, $this->seriesRepository, $this->rentals, $this->copier, new DateTimeImmutable('2027-01-20'));
        $result = $handler->handle(new EndRentalSeries($seriesId, true));

        $this->assertSame(['deleted' => 1, 'detached' => 0], $result);
        $this->assertNull($this->rentals->findById($fourth->id));
        $this->assertNotNull($this->rentals->findById($third->id));
        $this->assertTrue($this->seriesRepository->findById($seriesId)?->isEnded());
    }

    public function testEndSeriesCanKeepFutureOccurrencesAsOrdinaryRentals(): void
    {
        $this->source();
        $seriesId = $this->createSeries(self::weeklyRule(3));

        $handler = new EndRentalSeriesHandler($this->support, $this->seriesRepository, $this->rentals, $this->copier, new DateTimeImmutable('2027-01-20'));
        $result = $handler->handle(new EndRentalSeries($seriesId, false));

        $this->assertSame(['deleted' => 0, 'detached' => 1], $result);
        $this->assertCount(2, $this->rentals->findBySeries($seriesId));
    }

    public function testDetachAndContext(): void
    {
        $this->source();
        $seriesId = $this->createSeries(self::weeklyRule(3));
        [$first, $second, $third] = $this->rentals->findBySeries($seriesId);

        $contextHandler = new GetRentalSeriesContextHandler($this->rentals, $this->seriesRepository);
        $context = $contextHandler->handle(new GetRentalSeriesContext($second->id));
        $this->assertSame(2, $context?->position);
        $this->assertSame(3, $context?->total);
        $this->assertSame($first->id, $context?->previous?->id);
        $this->assertSame([$third->id], array_map(static fn (Rental $r) => $r->id, $context?->following ?? []));

        (new DetachRentalFromSeriesHandler($this->support, $this->rentals, $this->copier))->handle(new DetachRentalFromSeries($second->id));
        $this->assertNull($this->rentals->findById($second->id)?->seriesId);
        $this->assertNull($contextHandler->handle(new GetRentalSeriesContext($second->id)));
        $this->assertSame(2, $this->rentals->findById($third->id)?->seriesIndex);
    }
}
