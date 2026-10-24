<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Auth\WebauthnChallenge;
use App\Domain\Auth\WebauthnChallengeRepositoryInterface;
use App\Domain\Auth\WebauthnChallengeType;
use App\Infrastructure\WebAuthn\WebAuthnService;
use App\Shared\Uuid;
use DateInterval;
use DateTimeImmutable;

final class BeginPasskeyLoginHandler implements CommandHandlerInterface
{
    private const CHALLENGE_TTL_MINUTES = 5;

    public function __construct(
        private readonly WebauthnChallengeRepositoryInterface $challenges,
        private readonly WebAuthnService $webAuthn,
    ) {
    }

    /** @return array{options: array<string, mixed>, challengeId: string} */
    public function handle(CommandInterface $command): array
    {
        assert($command instanceof BeginPasskeyLogin);

        $challengeRaw = $this->webAuthn->generateChallenge();
        $now = new DateTimeImmutable();

        $challenge = new WebauthnChallenge(
            id: Uuid::v4(),
            // Der Benutzer ist beim discoverable-Login noch nicht bekannt
            // (das ist gerade der Sinn des usernameless Flows) — die
            // Challenge wird stattdessen über ihre eigene ID an die
            // konkrete Browser-Session gebunden, siehe
            // PasskeyController::loginOptions()/loginComplete().
            userId: null,
            challenge: WebAuthnService::base64UrlEncode($challengeRaw),
            type: WebauthnChallengeType::Authentication,
            expiresAt: $now->add(new DateInterval('PT' . self::CHALLENGE_TTL_MINUTES . 'M')),
            consumedAt: null,
            createdAt: $now,
        );
        $this->challenges->save($challenge);

        $options = $this->webAuthn->buildRequestOptions(challengeRaw: $challengeRaw, rpId: $command->rpId);

        return ['options' => $options, 'challengeId' => $challenge->id];
    }
}
