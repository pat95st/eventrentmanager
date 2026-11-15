<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Backup;

use App\Application\Backup\Command\RunAutomaticBackup;
use App\Application\Backup\Command\RunAutomaticBackupHandler;
use App\Application\Backup\Command\UpdateBackupSettings;
use App\Application\Backup\Command\UpdateBackupSettingsHandler;
use App\Domain\Backup\BackupInterval;
use App\Domain\Backup\BackupRunStatus;
use App\Domain\Backup\BackupRunTrigger;
use App\Domain\Backup\BackupSettings;
use App\Domain\Backup\DatabaseDumperInterface;
use App\Domain\DocumentStorage\DocumentStorageTarget;
use App\Domain\DocumentStorage\StorageProtocol;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Backup\BackupArchiveBuilder;
use App\Infrastructure\DocumentStorage\ShareClientInterface;
use App\Infrastructure\DocumentStorage\StorageClientFactoryInterface;
use App\Infrastructure\DocumentStorage\StorageClientInterface;
use App\Infrastructure\DocumentStorage\StorageUploadException;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Tests\Support\Fakes\InMemoryBackupRunRepository;
use Tests\Support\Fakes\InMemoryBackupSettingsRepository;
use Tests\Support\Fakes\InMemoryDocumentStorageTargetRepository;
use ZipArchive;

/** Automatic backup to document storage targets — settings validation and the upload run, without network or database. */
final class AutomaticBackupTest extends Unit
{
    private string $workDirectory;
    private InMemoryBackupSettingsRepository $settings;
    private InMemoryDocumentStorageTargetRepository $targets;
    private InMemoryBackupRunRepository $runs;
    /** @var array<string, array<string, string>> target id => remote path => contents */
    private array $uploads = [];
    /** @var list<string> */
    private array $failingTargets = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->workDirectory = sys_get_temp_dir() . '/automatic-backup-test-' . bin2hex(random_bytes(4));
        mkdir($this->workDirectory . '/uploads/articles', 0775, true);
        file_put_contents($this->workDirectory . '/uploads/articles/bild.jpg', 'JPEG');

        $this->settings = new InMemoryBackupSettingsRepository();
        $this->targets = new InMemoryDocumentStorageTargetRepository();
        $this->runs = new InMemoryBackupRunRepository();
        $this->addTarget('nextcloud');
        $this->addTarget('sftp');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->workDirectory);
        parent::tearDown();
    }

    public function testUploadsBackupZipToEverySelectedTarget(): void
    {
        $this->settings->settings = new BackupSettings(enabled: true, storageTargetIds: ['nextcloud', 'sftp'], targetPath: 'Backups/{year}/{filename}');

        $run = $this->handler()->handle(new RunAutomaticBackup(BackupRunTrigger::Cron));

        $this->assertSame(BackupRunStatus::Success, $run->status);
        $this->assertSame(BackupRunTrigger::Cron, $run->trigger);
        $this->assertCount(2, $run->targetResults);
        $this->assertCount(1, $this->runs->runs);

        $remotePath = 'Backups/' . date('Y') . '/' . $run->fileName;
        $this->assertSame([$remotePath], array_keys($this->uploads['nextcloud']));
        $this->assertSame([$remotePath], array_keys($this->uploads['sftp']));
        $this->assertSame(strlen($this->uploads['sftp'][$remotePath]), $run->fileSize);

        // The uploaded bytes are a complete backup archive.
        $zipPath = $this->workDirectory . '/check.zip';
        file_put_contents($zipPath, $this->uploads['nextcloud'][$remotePath]);
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipPath));
        $this->assertSame('-- dump', $zip->getFromName('database.sql'));
        $this->assertSame('JPEG', $zip->getFromName('assets/articles/bild.jpg'));
        $zip->close();

        // The temporary archive is removed afterwards.
        $this->assertSame([], glob($this->workDirectory . '/tmp/*.zip'));
    }

    public function testOneFailingTargetDoesNotStopTheOthers(): void
    {
        $this->settings->settings = new BackupSettings(enabled: true, storageTargetIds: ['nextcloud', 'sftp']);
        $this->failingTargets = ['nextcloud'];

        $run = $this->handler()->handle(new RunAutomaticBackup(BackupRunTrigger::Manual));

        $this->assertSame(BackupRunStatus::Partial, $run->status);
        $this->assertFalse($run->targetResults[0]['success']);
        $this->assertSame('Server nicht erreichbar.', $run->targetResults[0]['message']);
        $this->assertTrue($run->targetResults[1]['success']);
        $this->assertCount(1, $this->uploads['sftp']);
    }

    public function testFailsWhenAllTargetsFail(): void
    {
        $this->settings->settings = new BackupSettings(enabled: true, storageTargetIds: ['nextcloud']);
        $this->failingTargets = ['nextcloud'];

        $run = $this->handler()->handle(new RunAutomaticBackup(BackupRunTrigger::Cron));

        $this->assertSame(BackupRunStatus::Failed, $run->status);
    }

    public function testSkipsDisabledTargetsAndLogsFailureWithoutTargets(): void
    {
        $this->targets->findById('sftp')->enabled = false;
        $this->settings->settings = new BackupSettings(enabled: true, storageTargetIds: ['sftp']);

        $run = $this->handler()->handle(new RunAutomaticBackup(BackupRunTrigger::Cron));

        $this->assertSame(BackupRunStatus::Failed, $run->status);
        $this->assertNotNull($run->message);
        $this->assertNull($run->fileName);
        $this->assertSame([], $this->uploads);
        $this->assertCount(1, $this->runs->runs);
    }

    public function testSavesValidSettings(): void
    {
        $this->settingsHandler()->handle(new UpdateBackupSettings(
            enabled: true,
            storageTargetIds: ['sftp', 'sftp', ''],
            targetPath: ' Sicherungen/{year}/{month}/{filename} ',
            interval: 'weekly',
            runTime: '02:30',
            weekday: '7',
            monthDay: '',
        ));

        $saved = $this->settings->settings;
        $this->assertTrue($saved->enabled);
        $this->assertSame(['sftp'], $saved->storageTargetIds);
        $this->assertSame('Sicherungen/{year}/{month}/{filename}', $saved->targetPath);
        $this->assertSame(BackupInterval::Weekly, $saved->interval);
        $this->assertSame('30 2 * * 0', $saved->cronSchedule());
    }

    public function testEmptyPathFallsBackToDefault(): void
    {
        $this->settingsHandler()->handle(new UpdateBackupSettings(false, [], '', 'daily', '03:15', '1', '1'));

        $this->assertSame(BackupSettings::DEFAULT_TARGET_PATH, $this->settings->settings->targetPath);
    }

    public function testRejectsInvalidSettings(): void
    {
        try {
            $this->settingsHandler()->handle(new UpdateBackupSettings(
                enabled: true,
                storageTargetIds: ['unknown'],
                targetPath: 'Sicherungen/',
                interval: 'hourly',
                runTime: '25:00',
                weekday: '8',
                monthDay: '31',
            ));
            $this->fail('ValidationException expected.');
        } catch (ValidationException $e) {
            $this->assertEqualsCanonicalizing(
                ['backup_storage_targets', 'backup_target_path', 'backup_interval', 'backup_run_time', 'backup_weekday', 'backup_month_day'],
                array_keys($e->errors()),
            );
        }
    }

    public function testEnabledRequiresAStorageTarget(): void
    {
        $this->expectException(ValidationException::class);

        $this->settingsHandler()->handle(new UpdateBackupSettings(true, [], null, 'daily', '03:15', null, null));
    }

    private function handler(): RunAutomaticBackupHandler
    {
        $dumper = new class () implements DatabaseDumperInterface {
            public function dumpToFile(string $targetPath): array
            {
                file_put_contents($targetPath, '-- dump');

                return ['tableCount' => 1, 'rowCount' => 2];
            }
        };

        return new RunAutomaticBackupHandler(
            $this->settings,
            $this->targets,
            $this->clientFactory(),
            new BackupArchiveBuilder($dumper, $this->workDirectory . '/uploads', $this->workDirectory . '/tmp'),
            $this->runs,
        );
    }

    private function settingsHandler(): UpdateBackupSettingsHandler
    {
        return new UpdateBackupSettingsHandler($this->settings, $this->targets);
    }

    private function clientFactory(): StorageClientFactoryInterface
    {
        $test = $this;

        return new class ($test) implements StorageClientFactoryInterface {
            public function __construct(private readonly AutomaticBackupTest $test)
            {
            }

            public function create(DocumentStorageTarget $target): StorageClientInterface
            {
                return new class ($this->test, $target->id) implements StorageClientInterface {
                    public function __construct(private readonly AutomaticBackupTest $test, private readonly string $targetId)
                    {
                    }

                    public function upload(string $contents, string $remotePath): void
                    {
                        $this->test->recordUpload($this->targetId, $remotePath, $contents);
                    }

                    public function testConnection(): string
                    {
                        return 'ok';
                    }

                    public function createDirectory(string $remotePath): void
                    {
                    }

                    public function moveDirectory(string $fromPath, string $toPath): void
                    {
                    }

                    public function deleteDirectory(string $remotePath): void
                    {
                    }
                };
            }

            public function createShareClient(DocumentStorageTarget $target): ?ShareClientInterface
            {
                return null;
            }
        };
    }

    /** @internal Called by the fake storage client. */
    public function recordUpload(string $targetId, string $remotePath, string $contents): void
    {
        if (in_array($targetId, $this->failingTargets, true)) {
            throw new StorageUploadException('Server nicht erreichbar.');
        }
        $this->uploads[$targetId][$remotePath] = $contents;
    }

    private function addTarget(string $id): void
    {
        $now = new DateTimeImmutable();
        $this->targets->save(new DocumentStorageTarget(
            id: $id,
            name: strtoupper($id),
            protocol: StorageProtocol::Sftp,
            host: 'backup.example',
            port: null,
            username: 'verleih',
            password: 'geheim',
            privateKey: null,
            privateKeyPassphrase: null,
            basePath: null,
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
        ));
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($directory);
    }
}
