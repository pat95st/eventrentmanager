<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Auth\WebauthnChallengeRepositoryInterface;
use App\Domain\Auth\WebauthnChallengeType;
use App\Domain\Auth\WebauthnCredentialRepositoryInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;
use App\Infrastructure\WebAuthn\WebAuthnException;
use App\Infrastructure\WebAuthn\WebAuthnService;
use DateTimeImmutable;

final class CompletePasskeyLoginHandler implements CommandHandlerInterface
{
    private const GENERIC_ERROR = ['passkey' => 'Die Anmeldung per Passkey ist fehlgeschlagen.'];

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly WebauthnCredentialRepositoryInterface $credentials,
        private readonly WebauthnChallengeRepositoryInterface $challenges,
        private readonly WebAuthnService $webAuthn,
    ) {
    }

    public function handle(CommandInterface $command): User
    {
        assert($command instanceof CompletePasskeyLogin);

        $challenge = $this->challenges->findById($command->challengeId);
        $now = new DateTimeImmutable();

        // Einmalverwendung: die Challenge wird sofort verbraucht, unabhängig
        // vom Ausgang der restlichen Prüfung (Replay-Schutz).
        if ($challenge !== null) {
            $this->challenges->markConsumed($challenge->id);
        }

        if (
            $challenge === null
            || $challenge->type !== WebauthnChallengeType::Authentication
            || $challenge->isConsumed()
            || $challenge->isExpired($now)
        ) {
            throw new ValidationException(self::GENERIC_ERROR);
        }

        try {
            $credentialIdB64 = $command->credentialIdB64;
            $credential = $this->credentials->findByCredentialId($credentialIdB64);
            if ($credential === null) {
                throw new WebAuthnException('Unbekannter Passkey.');
            }

            $clientDataJsonRaw = WebAuthnService::base64UrlDecode($command->clientDataJsonB64);
            $authenticatorDataRaw = WebAuthnService::base64UrlDecode($command->authenticatorDataB64);
            $signatureRaw = WebAuthnService::base64UrlDecode($command->signatureB64);

            $this->webAuthn->verifyClientData(
                clientDataJsonRaw: $clientDataJsonRaw,
                expectedType: 'webauthn.get',
                expectedChallengeRaw: WebAuthnService::base64UrlDecode($challenge->challenge),
                expectedOrigin: $command->expectedOrigin,
            );

            $authData = $this->webAuthn->parseAuthenticatorData($authenticatorDataRaw);
            if (!$authData['userPresent']) {
                throw new WebAuthnException('Der Authenticator hat keine Benutzerpräsenz bestätigt.');
            }

            // Die tatsächliche kryptografische Prüfung: ohne eine gültige,
            // zum gespeicherten öffentlichen Schlüssel passende Signatur
            // über authenticatorData || SHA-256(clientDataJSON) gibt es
            // keinen Login — das ist der sicherheitskritische Kern dieses
            // gesamten Features (siehe /tmp-Testharness in der Aufgabenstellung).
            $signatureValid = $this->webAuthn->verifyAssertionSignature(
                authenticatorDataRaw: $authenticatorDataRaw,
                clientDataJsonRaw: $clientDataJsonRaw,
                signatureRaw: $signatureRaw,
                storedCoseKey: $credential->publicKeyCose,
            );

            if (!$signatureValid) {
                throw new WebAuthnException('Signatur ist ungültig.');
            }

            // Klon-Erkennung (WebAuthn Level 2 §6.1.1 / §7.2 Schritt 21):
            // signCount darf nicht sinken, wenn beide Seiten einen von 0
            // verschiedenen Wert melden. Viele passkey-fähige, synchronisierte
            // Authenticatoren melden dauerhaft 0 — das wird hier bewusst
            // nicht als Fehler behandelt (gängige Praxis bei Passkeys).
            $newSignCount = $authData['signCount'];
            if ($credential->signCount !== 0 && $newSignCount !== 0 && $newSignCount <= $credential->signCount) {
                throw new WebAuthnException('Der Sign-Count ist nicht plausibel (möglicher geklonter Authenticator).');
            }
        } catch (WebAuthnException $e) {
            // Bewusst eine generische, nach außen identische Fehlermeldung
            // für alle Fehlschläge (falscher Passkey, ungültige Signatur,
            // falsche Origin, …) — verhindert, dass sich über
            // unterschiedliche Fehlermeldungen etwas über gültige
            // Credential-IDs/Konten erraten lässt.
            throw new ValidationException(self::GENERIC_ERROR);
        }

        $user = $this->users->findById($credential->userId);
        if ($user === null || !$user->isActive) {
            throw new ValidationException(self::GENERIC_ERROR);
        }

        $credential->signCount = max($credential->signCount, $newSignCount);
        $credential->lastUsedAt = $now;
        $this->credentials->save($credential);

        return $user;
    }
}
