<?php

declare(strict_types=1);

namespace Tests\Unit\Application\User;

use App\Application\User\Command\CompleteOnboardingTour;
use App\Application\User\Command\CompleteOnboardingTourHandler;
use App\Domain\Exception\NotFoundException;
use App\Domain\User\Permission;
use App\Domain\User\TwoFactorMethod;
use App\Domain\User\User;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Tests\Support\Fakes\InMemoryUserRepository;

/** The onboarding tour starts until the user finished or skipped it once. */
final class CompleteOnboardingTourHandlerTest extends Unit
{
    private InMemoryUserRepository $users;
    private CompleteOnboardingTourHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->users = new InMemoryUserRepository();
        $this->handler = new CompleteOnboardingTourHandler($this->users);
    }

    public function testNewUsersHaveNotSeenTheTour(): void
    {
        $this->assertNull($this->user('neu')->onboardingTourCompletedAt);
    }

    public function testCompletingRecordsTheTimestamp(): void
    {
        $this->user('neu');

        $this->handler->handle(new CompleteOnboardingTour('neu'));

        $this->assertNotNull($this->users->findById('neu')?->onboardingTourCompletedAt);
    }

    public function testReplayKeepsTheFirstTimestamp(): void
    {
        $first = new DateTimeImmutable('2026-01-02 03:04:05');
        $user = $this->user('alt');
        $user->onboardingTourCompletedAt = $first;

        $this->handler->handle(new CompleteOnboardingTour('alt'));

        $this->assertEquals($first, $this->users->findById('alt')?->onboardingTourCompletedAt);
    }

    public function testUnknownUserFails(): void
    {
        $this->expectException(NotFoundException::class);
        $this->handler->handle(new CompleteOnboardingTour('gibt-es-nicht'));
    }

    private function user(string $id): User
    {
        $now = new DateTimeImmutable();
        $user = new User($id, $id . '@example.test', null, null, null, [Permission::Kundenverwaltung], TwoFactorMethod::None, null, false, false, true, $now, $now);
        $this->users->save($user);

        return $user;
    }
}
