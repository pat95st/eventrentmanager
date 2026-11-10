<?php

declare(strict_types=1);

namespace Tests\Unit\Application\EventAudio;

use App\Application\EventAudio\Command\AudioPairingHandler;
use App\Application\EventAudio\Command\CreateAudioPairingCode;
use App\Application\EventAudio\Command\PairAudioPlayer;
use App\Domain\EventAudio\AudioPairingCode;
use App\Domain\EventAudio\AudioPairingCodeRepositoryInterface;
use App\Domain\EventAudio\AudioPlayer;
use App\Domain\EventAudio\AudioPlayerRepositoryInterface;
use App\Domain\EventAudio\RentalAudioSettings;
use App\Domain\EventAudio\RentalAudioSettingsRepositoryInterface;
use App\Domain\Exception\ValidationException;
use Codeception\Test\Unit;
use DateTimeImmutable;

/** Kopplung von Abspielgeräten per 6-stelligem Code (Migration 141). */
final class AudioPairingHandlerTest extends Unit
{
    private AudioPairingHandler $handler;
    private object $codes;
    private object $players;
    private object $rentalSettings;

    protected function _before(): void
    {
        $this->codes = new class () implements AudioPairingCodeRepositoryInterface {
            /** @var array<string, AudioPairingCode> */
            public array $items = [];
            /** @var array<int, array{0: string, 1: DateTimeImmutable}> */
            public array $failures = [];

            public function save(AudioPairingCode $code): void
            {
                $this->items[$code->id] = $code;
            }

            public function findUsable(string $code, DateTimeImmutable $now): ?AudioPairingCode
            {
                foreach ($this->items as $item) {
                    if ($item->code === $code && $item->isUsable($now)) {
                        return $item;
                    }
                }

                return null;
            }

            public function findActiveForRental(string $rentalId, DateTimeImmutable $now): ?AudioPairingCode
            {
                foreach (array_reverse($this->items) as $item) {
                    if ($item->rentalId === $rentalId && $item->isUsable($now)) {
                        return $item;
                    }
                }

                return null;
            }

            public function invalidateForRental(string $rentalId, DateTimeImmutable $now): void
            {
                foreach ($this->items as $id => $item) {
                    if ($item->rentalId === $rentalId && $item->isUsable($now)) {
                        $this->items[$id] = new AudioPairingCode($item->id, $item->rentalId, $item->code, $item->createdByName, $item->createdAt, $now);
                    }
                }
            }

            public function recordFailure(string $ip, DateTimeImmutable $at): void
            {
                $this->failures[] = [$ip, $at];
            }

            public function countFailuresSince(string $ip, DateTimeImmutable $since): int
            {
                return count(array_filter($this->failures, static fn (array $f): bool => $f[0] === $ip && $f[1] >= $since));
            }
        };
        $this->players = new class () implements AudioPlayerRepositoryInterface {
            /** @var array<string, AudioPlayer> */
            public array $items = [];

            public function findAll(): array
            {
                return array_values($this->items);
            }

            public function findById(string $id): ?AudioPlayer
            {
                return $this->items[$id] ?? null;
            }

            public function findByTokenHash(string $hash): ?AudioPlayer
            {
                foreach ($this->items as $p) {
                    if ($p->tokenHash === $hash) {
                        return $p;
                    }
                }

                return null;
            }

            public function save(AudioPlayer $player): void
            {
                $this->items[$player->id] = $player;
            }

            public function delete(string $id): void
            {
                unset($this->items[$id]);
            }

            public function touch(string $id, DateTimeImmutable $at, ?string $ip, ?string $version, ?string $nowPlaying): void
            {
            }
        };
        $this->rentalSettings = new class () implements RentalAudioSettingsRepositoryInterface {
            /** @var array<string, RentalAudioSettings> */
            public array $items = [];

            public function get(string $rentalId): RentalAudioSettings
            {
                return $this->items[$rentalId] ?? new RentalAudioSettings($rentalId);
            }

            public function save(RentalAudioSettings $settings): void
            {
                $this->items[$settings->rentalId] = $settings;
            }

            public function activeRentalIdForPlayer(string $playerId, DateTimeImmutable $now): ?string
            {
                return null;
            }
        };
        $this->handler = new AudioPairingHandler($this->codes, $this->players, $this->rentalSettings);
    }

    public function testCreatesSixDigitCodeAndInvalidatesPrevious(): void
    {
        $first = $this->handler->handle(new CreateAudioPairingCode('rental-1', 'Admin'));
        $second = $this->handler->handle(new CreateAudioPairingCode('rental-1', 'Admin'));
        $this->assertMatchesRegularExpression('/^\d{6}$/', $second->code);
        $now = new DateTimeImmutable();
        $this->assertSame($second->id, $this->codes->findActiveForRental('rental-1', $now)?->id);
        $this->assertFalse($this->codes->items[$first->id]->isUsable($now->modify('+1 second')));
    }

    public function testPairingCreatesPlayerAndAssignsRental(): void
    {
        $code = $this->handler->handle(new CreateAudioPairingCode('rental-1', 'Admin'));
        $result = $this->handler->handle(new PairAudioPlayer(substr($code->code, 0, 3) . ' ' . substr($code->code, 3), 'Pi FOH', null, '10.0.0.5'));
        $this->assertStringStartsWith('mvp_', (string) $result['token']);
        $this->assertSame('Pi FOH', $result['player']->name);
        $this->assertSame($result['player']->id, $this->rentalSettings->get('rental-1')->playerId);
        $this->assertSame($result['player']->id, $this->players->findByTokenHash(hash('sha256', (string) $result['token']))?->id);
    }

    public function testCodeCanOnlyBeUsedOnce(): void
    {
        $code = $this->handler->handle(new CreateAudioPairingCode('rental-1', 'Admin'));
        $this->handler->handle(new PairAudioPlayer($code->code, null, null, '10.0.0.5'));
        $this->expectException(ValidationException::class);
        $this->handler->handle(new PairAudioPlayer($code->code, null, null, '10.0.0.5'));
    }

    public function testExistingTokenReassignsPlayerWithoutNewToken(): void
    {
        $first = $this->handler->handle(new PairAudioPlayer($this->handler->handle(new CreateAudioPairingCode('rental-1', 'Admin'))->code, 'Pi', null, '10.0.0.5'));
        $code = $this->handler->handle(new CreateAudioPairingCode('rental-2', 'Admin'));
        $second = $this->handler->handle(new PairAudioPlayer($code->code, null, (string) $first['token'], '10.0.0.5'));
        $this->assertNull($second['token']);
        $this->assertSame($first['player']->id, $second['player']->id);
        $this->assertCount(1, $this->players->items);
        $this->assertSame($first['player']->id, $this->rentalSettings->get('rental-2')->playerId);
    }

    public function testBlocksAfterTooManyFailures(): void
    {
        for ($i = 0; $i < AudioPairingCode::MAX_FAILURES; $i++) {
            try {
                $this->handler->handle(new PairAudioPlayer('000000x', null, null, '10.0.0.9'));
            } catch (ValidationException) {
            }
        }
        $code = $this->handler->handle(new CreateAudioPairingCode('rental-1', 'Admin'));
        try {
            $this->handler->handle(new PairAudioPlayer($code->code, null, null, '10.0.0.9'));
            $this->fail('Erwartete Sperre nach zu vielen Fehlversuchen.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Fehlversuche', implode(' ', $e->errors()));
        }
    }
}
