<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\CalDavSigningSettingsRepositoryInterface;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Shared\Uuid;
use DateTimeImmutable;

final class UploadCalDavSigningCertificateHandler implements CommandHandlerInterface
{
    private const SUBDIRECTORY = 'caldav-signing';
    private const MAX_SIZE_BYTES = 1 * 1024 * 1024; // 1 MB reicht bei Weitem für ein PEM-Zertifikat/Privatschlüssel

    public function __construct(
        private readonly CalDavSigningSettingsRepositoryInterface $repository,
        private readonly LocalFileStorage $fileStorage,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UploadCalDavSigningCertificate);

        $certificateFile = $command->certificateFile;
        $privateKeyFile = $command->privateKeyFile;

        if ($certificateFile === null || $certificateFile['error'] === UPLOAD_ERR_NO_FILE) {
            throw new ValidationException(['certificate' => 'Bitte eine Zertifikatsdatei (PEM) auswählen.']);
        }
        if ($privateKeyFile === null || $privateKeyFile['error'] === UPLOAD_ERR_NO_FILE) {
            throw new ValidationException(['private_key' => 'Bitte die zugehörige Privatschlüssel-Datei (PEM) auswählen.']);
        }
        if ($certificateFile['size'] > self::MAX_SIZE_BYTES || $privateKeyFile['size'] > self::MAX_SIZE_BYTES) {
            throw new ValidationException(['certificate' => 'Die Dateien sind zu groß (maximal 1 MB je Datei).']);
        }

        $certificateContent = (string) file_get_contents($certificateFile['tmp_name']);
        $privateKeyContent = (string) file_get_contents($privateKeyFile['tmp_name']);

        $certificate = openssl_x509_read($certificateContent);
        if ($certificate === false) {
            throw new ValidationException(['certificate' => 'Das ist kein gültiges PEM-Zertifikat.']);
        }

        // Keine Passphrase-Unterstützung: die Anwendung muss den Schlüssel bei
        // jedem .mobileconfig-Download ohne Rückfrage verwenden können.
        $privateKey = openssl_pkey_get_private($privateKeyContent);
        if ($privateKey === false) {
            throw new ValidationException(['private_key' => 'Das ist kein gültiger, unverschlüsselter PEM-Privatschlüssel (eine Passphrase wird nicht unterstützt).']);
        }

        if (!openssl_x509_check_private_key($certificate, $privateKey)) {
            throw new ValidationException(['private_key' => 'Zertifikat und Privatschlüssel passen nicht zusammen.']);
        }

        $settings = $this->repository->get();

        if ($settings->certificateFilename !== null) {
            $this->fileStorage->delete(self::SUBDIRECTORY, $settings->certificateFilename);
        }
        if ($settings->privateKeyFilename !== null) {
            $this->fileStorage->delete(self::SUBDIRECTORY, $settings->privateKeyFilename);
        }

        $storedCertificate = $this->fileStorage->storeUploadedFile($certificateFile, self::SUBDIRECTORY, Uuid::v4());
        $storedPrivateKey = $this->fileStorage->storeUploadedFile($privateKeyFile, self::SUBDIRECTORY, Uuid::v4());

        $settings->certificateFilename = $storedCertificate['storedFilename'];
        $settings->privateKeyFilename = $storedPrivateKey['storedFilename'];
        $settings->isSelfSigned = false;
        $settings->updatedAt = new DateTimeImmutable();

        $this->repository->save($settings);

        return null;
    }
}
