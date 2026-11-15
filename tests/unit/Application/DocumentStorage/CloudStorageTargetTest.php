<?php

declare(strict_types=1);

namespace Tests\Unit\Application\DocumentStorage;

use App\Application\DocumentStorage\Command\CompleteStorageTargetOAuth;
use App\Application\DocumentStorage\Command\CompleteStorageTargetOAuthHandler;
use App\Application\DocumentStorage\Command\CreateStorageTarget;
use App\Application\DocumentStorage\Command\CreateStorageTargetHandler;
use App\Application\DocumentStorage\Command\DisconnectStorageTargetOAuth;
use App\Application\DocumentStorage\Command\DisconnectStorageTargetOAuthHandler;
use App\Application\DocumentStorage\Command\UpdateStorageTarget;
use App\Application\DocumentStorage\Command\UpdateStorageTargetHandler;
use App\Domain\DocumentStorage\StorageProtocol;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\DocumentStorage\Cloud\CloudOAuthClient;
use Codeception\Test\Unit;
use Tests\Support\Fakes\FakeCloudHttpClient;
use Tests\Support\Fakes\InMemoryDocumentStorageTargetRepository;

/** Creating, updating and connecting Dropbox/OneDrive/Google Drive/Box and iCloud Drive targets. */
final class CloudStorageTargetTest extends Unit
{
    private InMemoryDocumentStorageTargetRepository $targets;

    protected function _before(): void
    {
        $this->targets = new InMemoryDocumentStorageTargetRepository();
    }

    public function testOAuthTargetNeedsClientCredentialsButNoHost(): void
    {
        try {
            $this->create('dropbox', []);
            $this->fail('ValidationException expected');
        } catch (ValidationException $e) {
            $this->assertSame(['oauthClientId', 'oauthClientSecret'], array_keys($e->errors()));
        }

        $id = $this->create('onedrive', ['oauthClientId' => ' app ', 'oauthClientSecret' => 'secret', 'oauthTenant' => 'contoso.onmicrosoft.com']);
        $target = $this->targets->findById($id);
        $this->assertSame(StorageProtocol::OneDrive, $target?->protocol);
        $this->assertSame('app', $target?->oauthClientId);
        $this->assertSame('contoso.onmicrosoft.com', $target?->oauthTenant);
        $this->assertFalse($target?->isOAuthConnected());
    }

    public function testICloudNeedsAbsoluteLocalFolder(): void
    {
        foreach (['' => true, 'iCloud/Verleih' => true, '/Users/a/../b' => true, '/Users/verleih/iCloud' => false] as $path => $invalid) {
            try {
                $this->create('icloud', [], (string) $path);
                $this->assertFalse($invalid, "\"{$path}\" should be rejected");
            } catch (ValidationException $e) {
                $this->assertTrue($invalid, "\"{$path}\" should be accepted");
                $this->assertArrayHasKey('basePath', $e->errors());
            }
        }
    }

    public function testUpdateKeepsSecretAndConnectionButChangingAppDisconnects(): void
    {
        $id = $this->create('box', ['oauthClientId' => 'app', 'oauthClientSecret' => 'secret']);
        $target = $this->targets->findById($id);
        $target->oauthRefreshToken = 'refresh';
        $target->oauthAccessToken = 'access';

        $this->update($id, 'box', ['oauthClientId' => 'app', 'oauthClientSecret' => '']);
        $this->assertSame('secret', $target->oauthClientSecret);
        $this->assertTrue($target->isOAuthConnected());

        $this->update($id, 'box', ['oauthClientId' => 'other-app', 'oauthClientSecret' => '']);
        $this->assertFalse($target->isOAuthConnected());
        $this->assertNull($target->oauthAccessToken);

        // The stored secret belongs to the Box app — switching service needs a new one.
        $this->expectException(ValidationException::class);
        $this->update($id, 'google_drive', ['oauthClientId' => 'other-app', 'oauthClientSecret' => '']);
    }

    public function testCompleteOAuthStoresTokensAndAccount(): void
    {
        $id = $this->create('google_drive', ['oauthClientId' => 'app', 'oauthClientSecret' => 'secret']);
        $http = new FakeCloudHttpClient();
        $http->on('POST', 'https://oauth2.googleapis.com/token', function (string $m, string $u, array $h, ?string $body) {
            parse_str((string) $body, $fields);
            $this->assertSame(['grant_type' => 'authorization_code', 'code' => 'abc', 'redirect_uri' => 'https://verleih.example/cb', 'client_id' => 'app', 'client_secret' => 'secret'], $fields);

            return FakeCloudHttpClient::json(200, ['access_token' => 'access', 'refresh_token' => 'refresh', 'expires_in' => 3599]);
        });
        $http->on('GET', 'https://www.googleapis.com/drive/v3/about', FakeCloudHttpClient::json(200, ['user' => ['displayName' => 'Anna', 'emailAddress' => 'anna@example.org']]));

        $account = (new CompleteStorageTargetOAuthHandler($this->targets, new CloudOAuthClient($http)))
            ->handle(new CompleteStorageTargetOAuth($id, 'abc', 'https://verleih.example/cb'));

        $target = $this->targets->findById($id);
        $this->assertSame('anna@example.org', $account);
        $this->assertTrue($target?->isOAuthConnected());
        $this->assertSame('access', $target?->oauthAccessToken);
        $this->assertNotNull($target?->oauthConnectedAt);

        (new DisconnectStorageTargetOAuthHandler($this->targets))->handle(new DisconnectStorageTargetOAuth($id));
        $this->assertFalse($target?->isOAuthConnected());
        $this->assertSame('secret', $target?->oauthClientSecret);
    }

    public function testCompleteOAuthWithoutRefreshTokenIsRejected(): void
    {
        $id = $this->create('dropbox', ['oauthClientId' => 'app', 'oauthClientSecret' => 'secret']);
        $http = new FakeCloudHttpClient();
        $http->on('POST', 'https://api.dropboxapi.com/oauth2/token', FakeCloudHttpClient::json(200, ['access_token' => 'access', 'expires_in' => 14400]));

        $this->expectException(ValidationException::class);
        (new CompleteStorageTargetOAuthHandler($this->targets, new CloudOAuthClient($http)))
            ->handle(new CompleteStorageTargetOAuth($id, 'abc', 'https://verleih.example/cb'));
    }

    /** @param array<string, mixed> $cloud */
    private function create(string $protocol, array $cloud, string $basePath = ''): string
    {
        return (new CreateStorageTargetHandler($this->targets))->handle(new CreateStorageTarget(
            name: 'Cloud',
            protocol: $protocol,
            host: '',
            port: '',
            username: '',
            password: null,
            privateKey: null,
            privateKeyPassphrase: null,
            basePath: $basePath,
            webdavUseHttps: true,
            webdavAllowSelfSigned: false,
            s3AccessKeyId: '',
            s3SecretAccessKey: null,
            s3Bucket: '',
            s3Region: '',
            s3Endpoint: '',
            s3UsePathStyle: false,
            enabled: true,
            cloudSettings: $cloud,
        ));
    }

    /** @param array<string, mixed> $cloud */
    private function update(string $id, string $protocol, array $cloud): void
    {
        (new UpdateStorageTargetHandler($this->targets))->handle(new UpdateStorageTarget(
            id: $id,
            name: 'Cloud',
            protocol: $protocol,
            host: '',
            port: '',
            username: '',
            password: null,
            privateKey: null,
            privateKeyPassphrase: null,
            basePath: 'Verleih',
            webdavUseHttps: true,
            webdavAllowSelfSigned: false,
            s3AccessKeyId: '',
            s3SecretAccessKey: null,
            s3Bucket: '',
            s3Region: '',
            s3Endpoint: '',
            s3UsePathStyle: false,
            enabled: true,
            cloudSettings: $cloud,
        ));
    }
}
