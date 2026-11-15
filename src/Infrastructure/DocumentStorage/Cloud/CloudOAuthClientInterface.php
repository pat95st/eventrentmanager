<?php

declare(strict_types=1);

namespace App\Infrastructure\DocumentStorage\Cloud;

use App\Domain\DocumentStorage\DocumentStorageTarget;
use App\Infrastructure\DocumentStorage\StorageUploadException;

/** Token requests against the OAuth endpoints of the cloud storage services. */
interface CloudOAuthClientInterface
{
    /** @throws StorageUploadException */
    public function exchangeCode(DocumentStorageTarget $target, string $redirectUri, string $code): OAuthTokenSet;

    /**
     * Uses the stored refresh token. The returned set contains the new
     * refresh token if the provider rotated it, otherwise the old one.
     *
     * @throws StorageUploadException
     */
    public function refresh(DocumentStorageTarget $target): OAuthTokenSet;

    /** Name/e-mail of the connected account for display, null if it cannot be determined. */
    public function accountLabel(DocumentStorageTarget $target, string $accessToken): ?string;
}
