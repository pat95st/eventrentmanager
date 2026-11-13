<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\DocumentStorage;

use App\Domain\DocumentStorage\DocumentStorageTarget;
use App\Domain\DocumentStorage\StorageProtocol;
use App\Infrastructure\DocumentStorage\OcsShareClient;
use App\Infrastructure\DocumentStorage\StorageClientFactory;
use App\Infrastructure\DocumentStorage\StorageUploadException;
use App\Infrastructure\DocumentStorage\WebDavEndpoint;
use Codeception\Test\Unit;
use DateTimeImmutable;

/** URL-Aufbau für WebDAV/Nextcloud/ownCloud und das Auswerten der OCS-Antworten — ohne Netzwerk. */
final class CloudEndpointTest extends Unit
{
    public function testPlainWebDavKeepsBasePathAsFullUrlPath(): void
    {
        $target = $this->target(StorageProtocol::WebDav, 'cloud.example.org', '/remote.php/dav/files/anna/Verleih/');

        $this->assertSame(
            'https://cloud.example.org/remote.php/dav/files/anna/Verleih/Kunden/M%C3%BCller%20GmbH',
            WebDavEndpoint::fileUrl($target, 'Kunden/Müller GmbH'),
        );
    }

    public function testNextcloudAddsDavPathAndUsesBasePathAsSubfolder(): void
    {
        $target = $this->target(StorageProtocol::Nextcloud, 'cloud.example.org', 'Verleih', username: 'anna@example.org', port: 8443);

        $this->assertSame(
            'https://cloud.example.org:8443/remote.php/dav/files/anna%40example.org/Verleih/Kunden/A%20B',
            WebDavEndpoint::fileUrl($target, '/Kunden/A B/'),
        );
        $this->assertSame('/Verleih/Kunden/A B', WebDavEndpoint::userFilesPath($target, 'Kunden/A B'));
    }

    public function testCloudInSubdirectoryAndPastedScheme(): void
    {
        $target = $this->target(StorageProtocol::OwnCloud, 'https://example.org/cloud/', null, https: false);

        $this->assertSame('http://example.org/cloud', WebDavEndpoint::rootUrl($target));
        $this->assertSame('http://example.org/cloud/remote.php/dav/files/anna/Events', WebDavEndpoint::fileUrl($target, 'Events'));
        $this->assertSame('/Events', WebDavEndpoint::userFilesPath($target, 'Events'));
    }

    public function testOnlyNextcloudAndOwnCloudOfferSharing(): void
    {
        $factory = new StorageClientFactory();

        $this->assertNotNull($factory->createShareClient($this->target(StorageProtocol::Nextcloud, 'c.example', null)));
        $this->assertNotNull($factory->createShareClient($this->target(StorageProtocol::OwnCloud, 'c.example', null)));
        $this->assertNull($factory->createShareClient($this->target(StorageProtocol::WebDav, 'c.example', null)));
        $this->assertNull($factory->createShareClient($this->target(StorageProtocol::Sftp, 'c.example', null)));
    }

    public function testParsesSuccessfulOcsResponses(): void
    {
        $v2 = '{"ocs":{"meta":{"status":"ok","statuscode":200,"message":"OK"},"data":{"id":"42","url":"https://c.example/s/abc"}}}';
        $v1 = '{"ocs":{"meta":{"status":"ok","statuscode":100,"message":null},"data":{"id":7,"token":"xyz"}}}';

        $this->assertSame('https://c.example/s/abc', OcsShareClient::parseResponse(200, $v2)['url']);
        $this->assertSame(7, OcsShareClient::parseResponse(200, $v1)['id']);
    }

    public function testServerMessageIsPassedOnWhenShareIsRejected(): void
    {
        $body = '{"ocs":{"meta":{"status":"failure","statuscode":400,"message":"Password needs to be at least 10 characters long"},"data":[]}}';

        try {
            OcsShareClient::parseResponse(400, $body);
            $this->fail('Erwartete StorageUploadException.');
        } catch (StorageUploadException $e) {
            $this->assertStringContainsString('at least 10 characters', $e->getMessage());
        }
    }

    public function testFailureInsideHttp200IsDetected(): void
    {
        // OCS v1 antwortet auch im Fehlerfall mit HTTP 200.
        $this->expectException(StorageUploadException::class);
        OcsShareClient::parseResponse(200, '{"ocs":{"meta":{"status":"failure","statuscode":404,"message":"Wrong path, file/folder doesn\'t exist"},"data":[]}}');
    }

    public function testUnauthorizedAndNonJsonErrors(): void
    {
        foreach ([[401, ''], [500, '<html>Internal Server Error</html>']] as [$code, $body]) {
            try {
                OcsShareClient::parseResponse($code, $body);
                $this->fail('Erwartete StorageUploadException.');
            } catch (StorageUploadException $e) {
                $this->assertStringContainsString((string) $code, $e->getMessage());
            }
        }
    }

    public function testDeletingAlreadyRemovedShareIsNotAnError(): void
    {
        $body = '{"ocs":{"meta":{"status":"failure","statuscode":404,"message":"Wrong share ID, share doesn\'t exist"},"data":[]}}';

        $this->assertSame([], OcsShareClient::parseResponse(404, $body, allowNotFound: true));
    }

    private function target(StorageProtocol $protocol, string $host, ?string $basePath, string $username = 'anna', ?int $port = null, bool $https = true): DocumentStorageTarget
    {
        $now = new DateTimeImmutable();

        return new DocumentStorageTarget(
            id: 'target-1',
            name: 'Cloud',
            protocol: $protocol,
            host: $host,
            port: $port,
            username: $username,
            password: 'geheim',
            privateKey: null,
            privateKeyPassphrase: null,
            basePath: $basePath,
            webdavUseHttps: $https,
            webdavAllowSelfSigned: false,
            s3AccessKeyId: null,
            s3SecretAccessKey: null,
            s3Bucket: null,
            s3Region: null,
            s3Endpoint: null,
            s3UsePathStyle: false,
            enabled: true,
            createdAt: $now,
            updatedAt: $now,
        );
    }
}
