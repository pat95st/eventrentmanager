<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\DocumentStorage\Command\CreateStorageAssignment;
use App\Application\DocumentStorage\Command\CreateStorageTarget;
use App\Application\DocumentStorage\Command\DeleteStorageAssignment;
use App\Application\DocumentStorage\Command\DeleteStorageTarget;
use App\Application\DocumentStorage\Command\TestStorageConnection;
use App\Application\DocumentStorage\Command\UpdateStorageTarget;
use App\Application\DocumentStorage\Query\ListStorageAssignments;
use App\Application\DocumentStorage\Query\ListStorageTargets;
use App\Application\DocumentStorage\Query\ListStorageUploadLog;
use App\Application\Query\QueryBus;
use App\Domain\DocumentStorage\DocumentStorageTarget;
use App\Domain\DocumentStorage\DocumentType;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\DocumentStorage\StorageUploadException;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use function App\Infrastructure\Http\t;

/**
 * Admin-Verwaltung der "Ziellaufwerke" (externe Speicherziele, siehe
 * DocumentStorageTarget) und ihrer Zuordnung zu Dokumenttypen (siehe
 * DocumentStorageAssignment) — analog im Aufbau zu LabelPrinterController
 * (Ziellaufwerke, mehrere Zeilen) kombiniert mit MailboxSettingsController
 * (Zuordnungen als kleine Unterliste je Formular, analog zu den erlaubten
 * Absendern des Beleg-Posteingangs).
 */
final class DocumentStorageSettingsController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function index(Request $request): void
    {
        $this->renderTargets(null, [], []);
    }

    public function create(Request $request): void
    {
        $this->renderTargets(null, [], []);
    }

    public function store(Request $request): void
    {
        $command = new CreateStorageTarget(
            name: $request->input('name', '') ?? '',
            protocol: $request->input('protocol', '') ?? '',
            host: $request->input('host', '') ?? '',
            port: $request->input('port', '') ?? '',
            username: $request->input('username', '') ?? '',
            password: $request->input('password') !== '' ? $request->input('password') : null,
            privateKey: $request->input('private_key') !== '' ? $request->input('private_key') : null,
            privateKeyPassphrase: $request->input('private_key_passphrase') !== '' ? $request->input('private_key_passphrase') : null,
            basePath: $request->input('base_path', '') ?? '',
            webdavUseHttps: $request->input('webdav_use_https') === '1',
            webdavAllowSelfSigned: $request->input('webdav_allow_self_signed') === '1',
            s3AccessKeyId: $request->input('s3_access_key_id', '') ?? '',
            s3SecretAccessKey: $request->input('s3_secret_access_key') !== '' ? $request->input('s3_secret_access_key') : null,
            s3Bucket: $request->input('s3_bucket', '') ?? '',
            s3Region: $request->input('s3_region', '') ?? '',
            s3Endpoint: $request->input('s3_endpoint', '') ?? '',
            s3UsePathStyle: $request->input('s3_use_path_style') === '1',
            enabled: $request->input('enabled') === '1',
        );

        try {
            $this->commands->dispatch($command);
            Flash::success(t('settings.document_storage.targets.flash.created'));
            Response::redirect('/einstellungen/speicherziele');
        } catch (ValidationException $e) {
            $this->renderTargets(null, $e->errors(), $this->oldFromTargetCommand($command));
        }
    }

    public function edit(Request $request): void
    {
        $id = $request->param('id');
        $target = $this->findTarget($id);

        if ($target === null) {
            Response::notFound(t('settings.document_storage.targets.not_found'));

            return;
        }

        $this->renderTargets($target, [], []);
    }

    public function update(Request $request): void
    {
        $id = $request->param('id');

        $command = new UpdateStorageTarget(
            id: $id,
            name: $request->input('name', '') ?? '',
            protocol: $request->input('protocol', '') ?? '',
            host: $request->input('host', '') ?? '',
            port: $request->input('port', '') ?? '',
            username: $request->input('username', '') ?? '',
            password: $request->input('password') !== '' ? $request->input('password') : null,
            privateKey: $request->input('private_key') !== '' ? $request->input('private_key') : null,
            privateKeyPassphrase: $request->input('private_key_passphrase') !== '' ? $request->input('private_key_passphrase') : null,
            basePath: $request->input('base_path', '') ?? '',
            webdavUseHttps: $request->input('webdav_use_https') === '1',
            webdavAllowSelfSigned: $request->input('webdav_allow_self_signed') === '1',
            s3AccessKeyId: $request->input('s3_access_key_id', '') ?? '',
            s3SecretAccessKey: $request->input('s3_secret_access_key') !== '' ? $request->input('s3_secret_access_key') : null,
            s3Bucket: $request->input('s3_bucket', '') ?? '',
            s3Region: $request->input('s3_region', '') ?? '',
            s3Endpoint: $request->input('s3_endpoint', '') ?? '',
            s3UsePathStyle: $request->input('s3_use_path_style') === '1',
            enabled: $request->input('enabled') === '1',
        );

        try {
            $this->commands->dispatch($command);
            Flash::success(t('settings.document_storage.targets.flash.updated'));
            Response::redirect('/einstellungen/speicherziele');
        } catch (ValidationException $e) {
            $this->renderTargets($this->findTarget($id), $e->errors(), $this->oldFromTargetCommand($command), $id);
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function destroy(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new DeleteStorageTarget($id));
            Flash::success(t('settings.document_storage.targets.flash.deleted'));
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/einstellungen/speicherziele');
    }

    /** Lädt sofort eine kleine Testdatei hoch und versucht sie wieder zu entfernen — zeigt das Ergebnis (Erfolg wie Fehler) als Flash-Meldung. */
    public function testConnection(Request $request): void
    {
        $id = $request->param('id');

        try {
            $resultMessage = $this->commands->dispatch(new TestStorageConnection($id));
            Flash::success($resultMessage);
        } catch (StorageUploadException $e) {
            Flash::error(t('settings.document_storage.targets.test.failed_prefix') . $e->getMessage());
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/einstellungen/speicherziele');
    }

    // --- Zuordnungen (Dokumenttyp -> Ziellaufwerk + Pfad) -----------------

    public function assignments(Request $request): void
    {
        $this->renderAssignments([], []);
    }

    public function storeAssignment(Request $request): void
    {
        $command = new CreateStorageAssignment(
            documentType: $request->input('document_type', '') ?? '',
            storageTargetId: $request->input('storage_target_id', '') ?? '',
            targetPath: $request->input('target_path', '') ?? '',
            enabled: $request->input('enabled') === '1',
        );

        try {
            $this->commands->dispatch($command);
            Flash::success(t('settings.document_storage.assignments.flash.created'));
        } catch (ValidationException $e) {
            $this->renderAssignments($e->errors(), [
                'document_type' => $command->documentType,
                'storage_target_id' => $command->storageTargetId,
                'target_path' => $command->targetPath,
            ]);

            return;
        }

        Response::redirect('/einstellungen/speicherziele/zuordnungen');
    }

    public function destroyAssignment(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new DeleteStorageAssignment($id));
            Flash::success(t('settings.document_storage.assignments.flash.deleted'));
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/einstellungen/speicherziele/zuordnungen');
    }

    /** @param DocumentStorageTarget[] $targets */
    private function findInList(array $targets, string $id): ?DocumentStorageTarget
    {
        foreach ($targets as $target) {
            if ($target->id === $id) {
                return $target;
            }
        }

        return null;
    }

    private function findTarget(string $id): ?DocumentStorageTarget
    {
        $targets = $this->queries->ask(new ListStorageTargets());

        return $this->findInList($targets, $id);
    }

    /** @param array<string, string> $errors @param array<string, mixed> $old */
    private function renderTargets(?DocumentStorageTarget $editing, array $errors, array $old, ?string $editingId = null): void
    {
        $targets = $this->queries->ask(new ListStorageTargets());

        echo View::page('settings/document-storage/targets', [
            'targets' => $targets,
            'editing' => $editing,
            'errors' => $errors,
            'old' => $old,
            'protocols' => \App\Domain\DocumentStorage\StorageProtocol::cases(),
        ], activeNav: 'settings-speicherziele', title: t('settings.document_storage.targets.title'));
    }

    /** @param array<string, string> $errors @param array<string, mixed> $old */
    private function renderAssignments(array $errors, array $old): void
    {
        $assignments = $this->queries->ask(new ListStorageAssignments());
        $targets = $this->queries->ask(new ListStorageTargets());
        $log = $this->queries->ask(new ListStorageUploadLog(50));

        $targetsById = [];
        foreach ($targets as $target) {
            $targetsById[$target->id] = $target;
        }

        echo View::page('settings/document-storage/assignments', [
            'assignments' => $assignments,
            'targets' => $targets,
            'targetsById' => $targetsById,
            'documentTypes' => DocumentType::cases(),
            'log' => $log,
            'errors' => $errors,
            'old' => $old,
        ], activeNav: 'settings-speicherziele', title: t('settings.document_storage.assignments.title'));
    }

    /** @return array<string, mixed> */
    private function oldFromTargetCommand(CreateStorageTarget|UpdateStorageTarget $command): array
    {
        return [
            'name' => $command->name,
            'protocol' => $command->protocol,
            'host' => $command->host,
            'port' => $command->port,
            'username' => $command->username,
            'base_path' => $command->basePath,
            'webdav_use_https' => $command->webdavUseHttps,
            'webdav_allow_self_signed' => $command->webdavAllowSelfSigned,
            's3_access_key_id' => $command->s3AccessKeyId,
            's3_bucket' => $command->s3Bucket,
            's3_region' => $command->s3Region,
            's3_endpoint' => $command->s3Endpoint,
            's3_use_path_style' => $command->s3UsePathStyle,
            'enabled' => $command->enabled,
        ];
    }
}
