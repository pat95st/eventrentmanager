<?php

declare(strict_types=1);

namespace App\Infrastructure\DocumentStorage;

use App\Domain\DocumentStorage\DocumentStorageTarget;
use App\Domain\DocumentStorage\DocumentStorageTargetRepositoryInterface;
use App\Domain\DocumentStorage\StorageProtocol;
use App\Infrastructure\DocumentStorage\Cloud\BoxClient;
use App\Infrastructure\DocumentStorage\Cloud\CloudHttpClientInterface;
use App\Infrastructure\DocumentStorage\Cloud\CloudOAuthClient;
use App\Infrastructure\DocumentStorage\Cloud\CloudOAuthClientInterface;
use App\Infrastructure\DocumentStorage\Cloud\CurlCloudHttpClient;
use App\Infrastructure\DocumentStorage\Cloud\DropboxClient;
use App\Infrastructure\DocumentStorage\Cloud\GoogleDriveClient;
use App\Infrastructure\DocumentStorage\Cloud\OAuthAccessTokenProvider;
use App\Infrastructure\DocumentStorage\Cloud\OneDriveClient;

/** Wählt anhand von DocumentStorageTarget::$protocol den passenden Protokoll-Client. */
final class StorageClientFactory implements StorageClientFactoryInterface
{
    private readonly CloudHttpClientInterface $http;
    private readonly CloudOAuthClientInterface $oauth;

    /**
     * @param DocumentStorageTargetRepositoryInterface|null $targets Persists refreshed
     *        OAuth tokens (required for Box, which rotates its refresh tokens).
     */
    public function __construct(
        private readonly ?DocumentStorageTargetRepositoryInterface $targets = null,
        ?CloudHttpClientInterface $http = null,
        ?CloudOAuthClientInterface $oauth = null,
    ) {
        $this->http = $http ?? new CurlCloudHttpClient();
        $this->oauth = $oauth ?? new CloudOAuthClient($this->http);
    }

    public function create(DocumentStorageTarget $target): StorageClientInterface
    {
        return match ($target->protocol) {
            StorageProtocol::WebDav, StorageProtocol::Nextcloud, StorageProtocol::OwnCloud => new WebDavClient($target),
            StorageProtocol::Ftps => new FtpsClient($target),
            StorageProtocol::Sftp => new SftpClient($target),
            StorageProtocol::Scp => new ScpClient($target),
            StorageProtocol::S3 => new S3Client($target),
            StorageProtocol::Dropbox => new DropboxClient($target, $this->http, $this->tokens($target)),
            StorageProtocol::OneDrive => new OneDriveClient($target, $this->http, $this->tokens($target)),
            StorageProtocol::GoogleDrive => new GoogleDriveClient($target, $this->http, $this->tokens($target)),
            StorageProtocol::Box => new BoxClient($target, $this->http, $this->tokens($target)),
            StorageProtocol::ICloud => new LocalDirectoryClient($target),
        };
    }

    public function createShareClient(DocumentStorageTarget $target): ?ShareClientInterface
    {
        return $target->protocol->supportsSharing() ? new OcsShareClient($target) : null;
    }

    private function tokens(DocumentStorageTarget $target): OAuthAccessTokenProvider
    {
        return new OAuthAccessTokenProvider($target, $this->oauth, $this->targets);
    }
}
