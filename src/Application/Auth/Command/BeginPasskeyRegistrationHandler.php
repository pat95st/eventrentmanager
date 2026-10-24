<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Auth\WebauthnChallenge;
use App\Domain\Auth\WebauthnChallengeRepositoryInterface;
use App\Domain\Auth\WebauthnChallengeType;
use App\Domain\Auth\WebauthnCredentialRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\User\UserRepositoryInterface;
use App\Infrastructure\WebAuthn\WebAuthnService;
use App\Shared\Uuid;
use DateInterval;
use DateTimeImmutable;

final class BeginPasskeyRegistrationHandler implements CommandHandlerInterface
{
    /** Challenges sind fünf Minuten gültig, danach muss neu begonnen werden. */
    private const CHALLENGE_TTL_MINUTES = 5;

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly WebauthnCredentialRepositoryInterface $credentials,
        private readonly WebauthnChallengeRepositoryInterface $challenges,
        private readonly WebAuthnService $webAuthn,
    ) {
    }

    /** @return array{options: array<string, mixed>, challengeId: string} */
    public function handle(CommandInterface $command): array
    {
        assert($command instanceof BeginPasskeyRegistration);

        $user = $this->users->findById($command->userId);
        if ($user === null) {
            throw new NotFoundException("Benutzer mit ID \"{$command->userId}\" wurde nicht gefunden.");
        }

        $challengeRaw = $this->webAuthn->generateChallenge();
        $now = new DateTimeImmutable();

        $challenge = new WebauthnChallenge(
            id: Uuid::v4(),
            userId: $user->id,
            challenge: WebAuthnService::base64UrlEncode($challengeRaw),
            type: WebauthnChallengeType::Registration,
            expiresAt: $now->add(new DateInterval('PT' . self::CHALLENGE_TTL_MINUTES . 'M')),
            consumedAt: null,
            createdAt: $now,
        );
        $this->challenges->save($challenge);

        // credentialId wird bereits als base64url-Text gespeichert (siehe
        // MariadbWebauthnCredentialRepository) — hier also unverändert
        // durchgereicht, nicht doppelt kodiert.
        $excludeCredentialIds = array_map(
            static fn ($credential) => $credential->credentialId,
            $this->credentials->findAllForUser($user->id),
        );

        $options = $this->webAuthn->buildCreationOptions(
            challengeRaw: $challengeRaw,
            rpId: $command->rpId,
            rpName: $command->rpName,
            userIdRaw: $user->id,
            userEmail: $user->email,
            excludeCredentialIds: $excludeCredentialIds,
        );

        return ['options' => $options, 'challengeId' => $challenge->id];
    }
}
