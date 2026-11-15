<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\DocumentStorage;

use App\Domain\DocumentStorage\DocumentStorageTarget;
use App\Domain\DocumentStorage\StorageProtocol;
use App\Infrastructure\DocumentStorage\Cloud\BoxClient;
use App\Infrastructure\DocumentStorage\Cloud\CloudHttpResponse;
use App\Infrastructure\DocumentStorage\Cloud\CloudOAuthEndpoints;
use App\Infrastructure\DocumentStorage\Cloud\DropboxClient;
use App\Infrastructure\DocumentStorage\Cloud\GoogleDriveClient;
use App\Infrastructure\DocumentStorage\Cloud\OneDriveClient;
use App\Infrastructure\DocumentStorage\LocalDirectoryClient;
use App\Infrastructure\DocumentStorage\StorageClientFactory;
use App\Infrastructure\DocumentStorage\StorageUploadException;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Tests\Support\Fakes\FakeCloudHttpClient;
use Tests\Support\Fakes\InMemoryDocumentStorageTargetRepository;

/** Dropbox, OneDrive, Google Drive, Box (OAuth) and iCloud Drive (local folder) — without network. */
final class CloudStorageClientsTest extends Unit
{
    private FakeCloudHttpClient $http;
    private InMemoryDocumentStorageTargetRepository $targets;

    protected function _before(): void
    {
        $this->http = new FakeCloudHttpClient();
        $this->targets = new InMemoryDocumentStorageTargetRepository();
    }

    // --- OAuth -------------------------------------------------------------

    public function testAuthorizeUrlsRequestOfflineAccess(): void
    {
        $dropbox = CloudOAuthEndpoints::authorizeUrl($this->target(StorageProtocol::Dropbox), 'https://verleih.example/cb', 'st');
        $this->assertStringStartsWith('https://www.dropbox.com/oauth2/authorize?', $dropbox);
        $this->assertStringContainsString('token_access_type=offline', $dropbox);
        $this->assertStringContainsString('redirect_uri=https%3A%2F%2Fverleih.example%2Fcb', $dropbox);
        $this->assertStringContainsString('state=st', $dropbox);

        $google = CloudOAuthEndpoints::authorizeUrl($this->target(StorageProtocol::GoogleDrive), 'https://verleih.example/cb', 'st');
        $this->assertStringContainsString('access_type=offline', $google);
        $this->assertStringContainsString('prompt=consent', $google);
        $this->assertStringContainsString(rawurlencode(CloudOAuthEndpoints::GOOGLE_SCOPE), $google);

        $oneDrive = $this->target(StorageProtocol::OneDrive);
        $this->assertStringStartsWith('https://login.microsoftonline.com/common/oauth2/v2.0/authorize?', CloudOAuthEndpoints::authorizeUrl($oneDrive, 'x', 'y'));
        $oneDrive->oauthTenant = 'contoso.onmicrosoft.com';
        $this->assertSame('https://login.microsoftonline.com/contoso.onmicrosoft.com/oauth2/v2.0/token', CloudOAuthEndpoints::tokenUrl($oneDrive));
        $this->assertStringContainsString('offline_access', CloudOAuthEndpoints::authorizeUrl($oneDrive, 'x', 'y'));

        $this->assertStringStartsWith('https://account.box.com/api/oauth2/authorize?', CloudOAuthEndpoints::authorizeUrl($this->target(StorageProtocol::Box), 'x', 'y'));
    }

    public function testExpiredTokenIsRefreshedAndRotatedRefreshTokenPersisted(): void
    {
        $target = $this->target(StorageProtocol::Box, expired: true);
        $target->basePath = null;
        $this->targets->save($target);
        $this->http->on('POST', 'https://api.box.com/oauth2/token', function (string $m, string $u, array $h, ?string $body) {
            parse_str((string) $body, $fields);
            $this->assertSame('refresh_token', $fields['grant_type']);
            $this->assertSame('refresh-old', $fields['refresh_token']);
            $this->assertSame('client', $fields['client_id']);

            return FakeCloudHttpClient::json(200, ['access_token' => 'access-new', 'refresh_token' => 'refresh-new', 'expires_in' => 3600]);
        });
        $this->http->on('GET', 'https://api.box.com/2.0/folders/0/items', FakeCloudHttpClient::json(200, ['entries' => [], 'total_count' => 0]));
        $this->http->on('POST', 'https://upload.box.com/api/2.0/files/content', FakeCloudHttpClient::json(201, ['entries' => [['id' => '9']]]));

        $this->factory()->create($target)->upload('pdf', 'Rechnung.pdf');

        $stored = $this->targets->findById($target->id);
        $this->assertSame('refresh-new', $stored?->oauthRefreshToken);
        $this->assertSame('access-new', $stored?->oauthAccessToken);
        $this->assertSame('Authorization: Bearer access-new', $this->http->requests[1]['headers'][1]);
    }

    public function testUnauthorizedResponseForcesOneRefreshAndRetry(): void
    {
        $target = $this->target(StorageProtocol::Dropbox);
        $this->http->on('POST', 'https://content.dropboxapi.com/2/files/upload', new CloudHttpResponse(401, '{"error_summary":"expired_access_token/"}'), once: true);
        $this->http->on('POST', 'https://api.dropboxapi.com/oauth2/token', FakeCloudHttpClient::json(200, ['access_token' => 'fresh', 'expires_in' => 14400]));
        $this->http->on('POST', 'https://content.dropboxapi.com/2/files/upload', FakeCloudHttpClient::json(200, ['id' => 'id:1']));

        $this->factory()->create($target)->upload('pdf', 'a.pdf');

        $this->assertCount(3, $this->http->requests);
        $this->assertSame('Bearer fresh', FakeCloudHttpClient::header($this->http->requests[2], 'Authorization'));
        // Dropbox does not rotate: the old refresh token stays.
        $this->assertSame('refresh-old', $target->oauthRefreshToken);
    }

    public function testNotConnectedTargetFailsWithHint(): void
    {
        $target = $this->target(StorageProtocol::OneDrive);
        $target->disconnectOAuth();

        $this->expectException(StorageUploadException::class);
        $this->expectExceptionMessage('noch nicht mit Microsoft OneDrive verbunden');
        $this->factory()->create($target)->upload('x', 'a.pdf');
    }

    // --- Dropbox -------------------------------------------------------------

    public function testDropboxUploadUsesBasePathAndAsciiSafeApiArg(): void
    {
        $this->http->on('POST', 'https://content.dropboxapi.com/2/files/upload', FakeCloudHttpClient::json(200, []));

        $this->dropbox()->upload('pdf', 'Rechnungen/2026/Müller.pdf');

        $arg = FakeCloudHttpClient::header($this->http->requests[0], 'Dropbox-API-Arg');
        $this->assertNotNull($arg);
        $this->assertMatchesRegularExpression('/^[\x20-\x7e]+$/', $arg);
        $this->assertSame('/Verleih/Rechnungen/2026/Müller.pdf', json_decode($arg, true)['path']);
        $this->assertSame('overwrite', json_decode($arg, true)['mode']);
    }

    public function testDropboxFolderOperations(): void
    {
        $this->http->on('POST', 'https://api.dropboxapi.com/2/files/create_folder_v2', new CloudHttpResponse(409, '{"error_summary":"path/conflict/folder/.."}'));
        $this->http->on('POST', 'https://api.dropboxapi.com/2/files/delete_v2', new CloudHttpResponse(409, '{"error_summary":"path_lookup/not_found/.."}'));
        $this->http->on('POST', 'https://api.dropboxapi.com/2/files/move_v2', new CloudHttpResponse(409, '{"error_summary":"to/conflict/folder/.."}'));

        $client = $this->dropbox();
        $client->createDirectory('Kunden/A'); // already exists: fine
        $client->deleteDirectory('Kunden/B'); // missing: fine

        $this->assertSame(['path' => '/Verleih/Kunden/A', 'autorename' => false], json_decode((string) $this->http->requests[0]['body'], true));

        $this->expectExceptionMessage('existiert bereits');
        $client->moveDirectory('Kunden/A', 'Kunden/B');
    }

    // --- OneDrive ------------------------------------------------------------

    public function testOneDriveSimpleUploadEncodesPath(): void
    {
        $this->http->on('PUT', 'https://graph.microsoft.com/', FakeCloudHttpClient::json(201, ['id' => '1']));

        $this->oneDrive()->upload('pdf', 'Angebote/A & B #1.pdf');

        $this->assertSame(
            ['PUT https://graph.microsoft.com/v1.0/me/drive/root:/Verleih/Angebote/A%20%26%20B%20%231.pdf:/content'],
            $this->http->log(),
        );
    }

    public function testOneDriveLargeUploadUsesSessionWithoutAuthorizationHeader(): void
    {
        $this->http->on('POST', 'https://graph.microsoft.com/v1.0/me/drive/root:/Verleih/big.pdf:/createUploadSession', FakeCloudHttpClient::json(200, ['uploadUrl' => 'https://upload.example/session']));
        $this->http->on('PUT', 'https://upload.example/session', FakeCloudHttpClient::json(202, []));

        $contents = str_repeat('x', 5 * 1024 * 1024);
        $this->oneDrive()->upload($contents, 'big.pdf');

        $chunks = array_values(array_filter($this->http->requests, static fn (array $r) => $r['url'] === 'https://upload.example/session'));
        $this->assertCount(2, $chunks);
        $this->assertNull(FakeCloudHttpClient::header($chunks[0], 'Authorization'));
        $this->assertSame('bytes 0-3276799/5242880', FakeCloudHttpClient::header($chunks[0], 'Content-Range'));
        $this->assertSame('bytes 3276800-5242879/5242880', FakeCloudHttpClient::header($chunks[1], 'Content-Range'));
    }

    public function testOneDriveCreatesMissingFolderLevels(): void
    {
        $base = 'https://graph.microsoft.com/v1.0/me/drive/root';
        $this->http->on('GET', $base . ':/Verleih:', FakeCloudHttpClient::json(200, ['id' => 'v', 'folder' => []]));
        $this->http->on('POST', $base . ':/Verleih/Kunden:/children', FakeCloudHttpClient::json(201, ['id' => 'k2']));
        $this->http->on('POST', $base . ':/Verleih:/children', FakeCloudHttpClient::json(201, ['id' => 'k']));

        $this->oneDrive()->createDirectory('Kunden/Müller');

        $this->assertSame([
            'GET ' . $base . ':/Verleih/Kunden/M%C3%BCller:?$select=id,folder',
            'GET ' . $base . ':/Verleih/Kunden:?$select=id,folder',
            'GET ' . $base . ':/Verleih:?$select=id,folder',
            'POST ' . $base . ':/Verleih:/children',
            'POST ' . $base . ':/Verleih/Kunden:/children',
        ], $this->http->log());
        $this->assertSame('Müller', json_decode((string) $this->http->requests[4]['body'], true)['name']);
    }

    // --- Google Drive --------------------------------------------------------

    public function testGoogleDriveUploadCreatesFoldersAndUsesResumableUpload(): void
    {
        $searches = [];
        $this->http->on('GET', 'https://www.googleapis.com/drive/v3/files?', function (string $m, string $url) use (&$searches) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $searches[] = $query['q'];

            return FakeCloudHttpClient::json(200, ['files' => str_contains($query['q'], "name = 'Verleih'") ? [['id' => 'base']] : []]);
        });
        $this->http->on('POST', 'https://www.googleapis.com/drive/v3/files?fields=id', FakeCloudHttpClient::json(200, ['id' => 'new-folder']));
        $this->http->on('POST', 'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable', new CloudHttpResponse(200, '', ['location' => 'https://upload.example/g']));
        $this->http->on('PUT', 'https://upload.example/g', FakeCloudHttpClient::json(200, ['id' => 'file-1']));

        $this->googleDrive()->upload('pdf', "O'Brien/Angebot.pdf");

        $this->assertSame("'root' in parents and name = 'Verleih' and trashed = false and mimeType = 'application/vnd.google-apps.folder'", $searches[0]);
        $this->assertSame("'base' in parents and name = 'O\\'Brien' and trashed = false and mimeType = 'application/vnd.google-apps.folder'", $searches[1]);
        $this->assertStringContainsString("mimeType != 'application/vnd.google-apps.folder'", $searches[2]);

        $folderCreate = $this->requestTo('POST', 'https://www.googleapis.com/drive/v3/files?fields=id');
        $this->assertSame(['name' => "O'Brien", 'mimeType' => 'application/vnd.google-apps.folder', 'parents' => ['base']], json_decode((string) $folderCreate['body'], true));
        $init = $this->requestTo('POST', 'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&fields=id');
        $this->assertSame(['name' => 'Angebot.pdf', 'parents' => ['new-folder']], json_decode((string) $init['body'], true));
        $this->assertSame('application/pdf', FakeCloudHttpClient::header($this->requestTo('PUT', 'https://upload.example/g'), 'Content-Type'));
    }

    public function testGoogleDriveDeleteMovesFolderToTrash(): void
    {
        $this->http->on('GET', 'https://www.googleapis.com/drive/v3/files?', FakeCloudHttpClient::json(200, ['files' => [['id' => 'f1']]]));
        $this->http->on('PATCH', 'https://www.googleapis.com/drive/v3/files/f1', FakeCloudHttpClient::json(200, ['id' => 'f1']));

        $this->googleDrive()->deleteDirectory('Kunden/A');

        $this->assertSame(['trashed' => true], json_decode((string) $this->requestTo('PATCH', 'https://www.googleapis.com/drive/v3/files/f1?fields=id')['body'], true));
    }

    // --- Box -----------------------------------------------------------------

    public function testBoxUploadOfExistingFileCreatesNewVersion(): void
    {
        $this->http->on('GET', 'https://api.box.com/2.0/folders/0/items', FakeCloudHttpClient::json(200, ['total_count' => 1, 'entries' => [['type' => 'folder', 'id' => '11', 'name' => 'verleih']]]));
        $this->http->on('GET', 'https://api.box.com/2.0/folders/11/items', FakeCloudHttpClient::json(200, ['total_count' => 1, 'entries' => [['type' => 'file', 'id' => '77', 'name' => 'Rechnung.pdf']]]));
        $this->http->on('POST', 'https://upload.box.com/api/2.0/files/77/content', FakeCloudHttpClient::json(201, []));

        $this->box()->upload('%PDF', 'Rechnung.pdf');

        $upload = $this->requestTo('POST', 'https://upload.box.com/api/2.0/files/77/content');
        $this->assertStringContainsString('name="attributes"', (string) $upload['body']);
        $this->assertStringContainsString('{"name":"Rechnung.pdf"}', (string) $upload['body']);
        $this->assertLessThan(strpos((string) $upload['body'], 'name="file"'), strpos((string) $upload['body'], 'name="attributes"'));
    }

    public function testBoxMoveIntoNewParent(): void
    {
        $this->http->on('GET', 'https://api.box.com/2.0/folders/0/items', FakeCloudHttpClient::json(200, ['total_count' => 1, 'entries' => [['type' => 'folder', 'id' => '11', 'name' => 'Verleih']]]));
        $this->http->on('GET', 'https://api.box.com/2.0/folders/11/items', FakeCloudHttpClient::json(200, ['total_count' => 1, 'entries' => [['type' => 'folder', 'id' => '12', 'name' => 'Alt']]]));
        $this->http->on('GET', 'https://api.box.com/2.0/folders/12/items', FakeCloudHttpClient::json(200, ['total_count' => 0, 'entries' => []]));
        $this->http->on('POST', 'https://api.box.com/2.0/folders?fields=id', FakeCloudHttpClient::json(201, ['id' => '13']));
        $this->http->on('PUT', 'https://api.box.com/2.0/folders/12', FakeCloudHttpClient::json(200, ['id' => '12']));

        $this->box()->moveDirectory('Alt', 'Archiv/Neu');

        $this->assertSame(['name' => 'Archiv', 'parent' => ['id' => '11']], json_decode((string) $this->requestTo('POST', 'https://api.box.com/2.0/folders?fields=id')['body'], true));
        $this->assertSame(['name' => 'Neu', 'parent' => ['id' => '13']], json_decode((string) $this->requestTo('PUT', 'https://api.box.com/2.0/folders/12?fields=id')['body'], true));
    }

    public function testParentDirectorySegmentsAreRejected(): void
    {
        $this->expectException(StorageUploadException::class);
        $this->box()->upload('x', '../geheim.pdf');
    }

    // --- iCloud Drive (local folder) -----------------------------------------

    public function testLocalDirectoryClientWritesMovesAndDeletes(): void
    {
        $root = sys_get_temp_dir() . '/mv-icloud-' . bin2hex(random_bytes(4));
        mkdir($root);
        $target = $this->target(StorageProtocol::ICloud);
        $target->basePath = $root . '/';
        $client = $this->factory()->create($target);
        $this->assertInstanceOf(LocalDirectoryClient::class, $client);

        try {
            $client->upload('pdf', 'Kunden/A/Rechnung.pdf');
            $this->assertSame('pdf', file_get_contents($root . '/Kunden/A/Rechnung.pdf'));
            $this->assertSame(['Rechnung.pdf'], array_values(array_diff(scandir($root . '/Kunden/A') ?: [], ['.', '..'])));

            $client->moveDirectory('Kunden/A', 'Kunden/B');
            $this->assertFileExists($root . '/Kunden/B/Rechnung.pdf');

            $client->deleteDirectory('Kunden/B');
            $this->assertDirectoryDoesNotExist($root . '/Kunden/B');
            $client->deleteDirectory('Kunden/B'); // missing: fine

            $this->assertStringStartsWith('Ordner ist beschreibbar', $client->testConnection());
        } finally {
            @rmdir($root . '/Kunden');
            @rmdir($root);
        }
    }

    public function testLocalDirectoryClientRequiresExistingFolder(): void
    {
        $target = $this->target(StorageProtocol::ICloud);
        $target->basePath = sys_get_temp_dir() . '/does-not-exist-' . bin2hex(random_bytes(4));

        $this->expectExceptionMessage('existiert auf dem Server nicht');
        (new LocalDirectoryClient($target))->upload('x', 'a.pdf');
    }

    // --- helpers ---------------------------------------------------------------

    private function factory(): StorageClientFactory
    {
        return new StorageClientFactory($this->targets, $this->http);
    }

    private function dropbox(): DropboxClient
    {
        $client = $this->factory()->create($this->target(StorageProtocol::Dropbox));
        assert($client instanceof DropboxClient);

        return $client;
    }

    private function oneDrive(): OneDriveClient
    {
        $client = $this->factory()->create($this->target(StorageProtocol::OneDrive));
        assert($client instanceof OneDriveClient);

        return $client;
    }

    private function googleDrive(): GoogleDriveClient
    {
        $client = $this->factory()->create($this->target(StorageProtocol::GoogleDrive));
        assert($client instanceof GoogleDriveClient);

        return $client;
    }

    private function box(): BoxClient
    {
        $client = $this->factory()->create($this->target(StorageProtocol::Box));
        assert($client instanceof BoxClient);

        return $client;
    }

    /** @return array{method: string, url: string, headers: list<string>, body: ?string} */
    private function requestTo(string $method, string $url): array
    {
        foreach ($this->http->requests as $request) {
            if ($request['method'] === $method && $request['url'] === $url) {
                return $request;
            }
        }
        $this->fail("No request {$method} {$url}; got:\n" . implode("\n", $this->http->log()));
    }

    private function target(StorageProtocol $protocol, bool $expired = false): DocumentStorageTarget
    {
        $now = new DateTimeImmutable();

        return new DocumentStorageTarget(
            id: 'target-' . $protocol->value,
            name: $protocol->label(),
            protocol: $protocol,
            host: null,
            port: null,
            username: null,
            password: null,
            privateKey: null,
            privateKeyPassphrase: null,
            basePath: '/Verleih/',
            webdavUseHttps: true,
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
            oauthClientId: 'client',
            oauthClientSecret: 'secret',
            oauthRefreshToken: 'refresh-old',
            oauthAccessToken: 'access-old',
            oauthAccessTokenExpiresAt: $expired ? new DateTimeImmutable('-1 minute') : new DateTimeImmutable('+1 hour'),
        );
    }
}
