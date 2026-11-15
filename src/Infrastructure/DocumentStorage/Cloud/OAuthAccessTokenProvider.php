<?php

declare(strict_types=1);

namespace App\Infrastructure\DocumentStorage\Cloud;

use App\Domain\DocumentStorage\DocumentStorageTarget;
use App\Domain\DocumentStorage\DocumentStorageTargetRepositoryInterface;
use App\Infrastructure\DocumentStorage\StorageUploadException;
use DateTimeImmutable;

/**
 * Supplies a valid access token for an OAuth storage target, refreshing it
 * when it is (about to be) expired and persisting the new tokens — required
 * because Box (and Microsoft) rotate the refresh token on every refresh.
 */
final class OAuthAccessTokenProvider
{
    private const EXPIRY_MARGIN_SECONDS = 60;

    public function __construct(
        private readonly DocumentStorageTarget $target,
        private readonly CloudOAuthClientInterface $oauth,
        private readonly ?DocumentStorageTargetRepositoryInterface $targets = null,
    ) {
    }

    /** @throws StorageUploadException */
    public function accessToken(bool $forceRefresh = false): string
    {
        if (!$this->target->isOAuthConnected()) {
            throw new StorageUploadException("Das Ziellaufwerk ist noch nicht mit {$this->target->protocol->label()} verbunden — bitte in den Einstellungen auf „Verbinden“ klicken.");
        }

        if (!$forceRefresh && $this->isValid($this->target)) {
            return (string) $this->target->oauthAccessToken;
        }

        // Another request may have refreshed the token in the meantime (and,
        // for rotating providers, invalidated our refresh token).
        $stored = $this->targets?->findById($this->target->id);
        if ($stored !== null && $stored->oauthRefreshToken !== null && $stored->oauthRefreshToken !== '') {
            $this->target->oauthRefreshToken = $stored->oauthRefreshToken;
            if (!$forceRefresh && $this->isValid($stored)) {
                $this->target->oauthAccessToken = $stored->oauthAccessToken;
                $this->target->oauthAccessTokenExpiresAt = $stored->oauthAccessTokenExpiresAt;

                return (string) $stored->oauthAccessToken;
            }
        }

        $tokens = $this->oauth->refresh($this->target);
        $this->target->oauthAccessToken = $tokens->accessToken;
        $this->target->oauthRefreshToken = $tokens->refreshToken;
        $this->target->oauthAccessTokenExpiresAt = $tokens->expiresAt;

        if ($this->targets !== null) {
            $persisted = $stored ?? $this->target;
            $persisted->oauthAccessToken = $tokens->accessToken;
            $persisted->oauthRefreshToken = $tokens->refreshToken;
            $persisted->oauthAccessTokenExpiresAt = $tokens->expiresAt;
            $this->targets->save($persisted);
        }

        return $tokens->accessToken;
    }

    private function isValid(DocumentStorageTarget $target): bool
    {
        return $target->oauthAccessToken !== null
            && $target->oauthAccessToken !== ''
            && $target->oauthAccessTokenExpiresAt !== null
            && $target->oauthAccessTokenExpiresAt > new DateTimeImmutable('+' . self::EXPIRY_MARGIN_SECONDS . ' seconds');
    }
}
