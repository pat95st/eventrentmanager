<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Auth\WebauthnChallengeRepositoryInterface;
use App\Domain\Auth\WebauthnChallengeType;
use App\Domain\Auth\WebauthnCredential;
use App\Domain\Auth\WebauthnCredentialRepositoryInterface;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\WebAuthn\WebAuthnException;
use App\Infrastructure\WebAuthn\WebAuthnService;
use App\Shared\Uuid;
use DateTimeImmutable;

final class CompletePasskeyRegistrationHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly WebauthnCredentialRepositoryInterface $credentials,
        private readonly WebauthnChallengeRepositoryInterface $challenges,
        private readonly WebAuthnService $webAuthn,
    ) {
    }

    public function handle(CommandInterface $command): WebauthnCredential
    {
        assert($command instanceof CompletePasskeyRegistration);

        $challenge = $this->challenges->findById($command->challengeId);
        $now = new DateTimeImmutable();

        // Egal ob die Verifikation gleich erfolgreich ist oder nicht: die
        // Challenge wird in jedem Fall sofort verbraucht (Einmalverwendung,
        // Replay-Schutz) — daher ganz am Anfang, nicht erst bei Erfolg.
        if ($challenge !== null) {
            $this->challenges->markConsumed($challenge->id);
        }

        if (
            $challenge === null
            || $challenge->type !== WebauthnChallengeType::Registration
            || $challenge->userId !== $command->userId
            || $challenge->isConsumed()
            || $challenge->isExpired($now)
        ) {
            throw new ValidationException(['passkey' => 'Die Registrierung ist abgelaufen oder ungültig. Bitte erneut versuchen.']);
        }

        try {
            $clientDataJsonRaw = WebAuthnService::base64UrlDecode($command->clientDataJsonB64);
            $attestationObjectRaw = WebAuthnService::base64UrlDecode($command->attestationObjectB64);

            $this->webAuthn->verifyClientData(
                clientDataJsonRaw: $clientDataJsonRaw,
                expectedType: 'webauthn.create',
                expectedChallengeRaw: WebAuthnService::base64UrlDecode($challenge->challenge),
                expectedOrigin: $command->expectedOrigin,
            );

            $attestation = $this->webAuthn->parseAttestationObject($attestationObjectRaw);
            $authData = $this->webAuthn->parseAuthenticatorData($attestation['authData']);

            if ($authData['credentialId'] === null || $authData['cosePublicKey'] === null) {
                throw new WebAuthnException('Der Authenticator hat keine Credential-Daten geliefert.');
            }

            $credentialIdB64 = WebAuthnService::base64UrlEncode($authData['credentialId']);
            if ($this->credentials->existsCredentialId($credentialIdB64)) {
                throw new WebAuthnException('Dieser Passkey ist bereits registriert.');
            }

            $publicKeyCose = $this->webAuthn->normalizeCoseKeyForStorage($authData['cosePublicKey']);
        } catch (WebAuthnException $e) {
            throw new ValidationException(['passkey' => 'Der Passkey konnte nicht registriert werden: ' . $e->getMessage()]);
        }

        $nickname = trim((string) $command->nickname);

        $credential = new WebauthnCredential(
            id: Uuid::v4(),
            userId: $command->userId,
            credentialId: $credentialIdB64,
            publicKeyCose: $publicKeyCose,
            signCount: $authData['signCount'],
            nickname: $nickname !== '' ? $nickname : null,
            createdAt: $now,
            lastUsedAt: null,
        );

        $this->credentials->save($credential);

        return $credential;
    }
}
