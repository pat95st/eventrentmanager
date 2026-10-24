<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\CalDav;

use App\Domain\Settings\CalDavSigningSettings;
use App\Domain\Settings\CalDavSigningSettingsRepositoryInterface;
use App\Domain\Settings\CompanySettingsRepositoryInterface;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Liefert das aktive Signatur-Zertifikat für heruntergeladene
 * .mobileconfig-Profile: ein vom Administrator hochgeladenes Zertifikat,
 * falls vorhanden — sonst ein selbstsigniertes, das EINMALIG erzeugt und
 * dauerhaft gespeichert wird (nicht bei jedem Download neu, sonst würde
 * jeder Download ein anderes, erneut nicht vertrautes Zertifikat liefern).
 */
final class CalDavSigningCertificateProvider
{
    private const SUBDIRECTORY = 'caldav-signing';

    public function __construct(
        private readonly CalDavSigningSettingsRepositoryInterface $repository,
        private readonly LocalFileStorage $fileStorage,
        private readonly CompanySettingsRepositoryInterface $companySettings,
    ) {
    }

    /** @return array{cert: string, key: string}|null null, wenn kein Zertifikat verfügbar ist (z. B. fehlende openssl-Erweiterung) */
    public function get(): ?array
    {
        $settings = $this->repository->get();

        $fromStorage = $this->readFromStorage($settings);
        if ($fromStorage !== null) {
            return $fromStorage;
        }

        return $this->generateAndPersistSelfSigned($settings);
    }

    /** @return array{cert: string, key: string}|null */
    private function readFromStorage(CalDavSigningSettings $settings): ?array
    {
        if ($settings->certificateFilename === null || $settings->privateKeyFilename === null) {
            return null;
        }

        $certPath = $this->fileStorage->absolutePath(self::SUBDIRECTORY, $settings->certificateFilename);
        $keyPath = $this->fileStorage->absolutePath(self::SUBDIRECTORY, $settings->privateKeyFilename);

        if (!is_file($certPath) || !is_file($keyPath)) {
            return null;
        }

        $cert = file_get_contents($certPath);
        $key = file_get_contents($keyPath);

        return $cert !== false && $key !== false ? ['cert' => $cert, 'key' => $key] : null;
    }

    /** @return array{cert: string, key: string}|null */
    private function generateAndPersistSelfSigned(CalDavSigningSettings $settings): ?array
    {
        $companyName = trim((string) $this->companySettings->get()->name);
        $commonName = $companyName !== '' ? $companyName . ' CalDAV' : 'Materialverleih CalDAV';

        $generated = CalDavSelfSignedCertificateGenerator::generate($commonName);
        if ($generated === null) {
            return null;
        }

        $storedCertificate = $this->fileStorage->storeRawBytes(
            $generated['cert'],
            self::SUBDIRECTORY,
            Uuid::v4(),
            'caldav-self-signed.crt',
            'application/x-pem-file'
        );
        $storedPrivateKey = $this->fileStorage->storeRawBytes(
            $generated['key'],
            self::SUBDIRECTORY,
            Uuid::v4(),
            'caldav-self-signed.key',
            'application/x-pem-file'
        );

        $settings->certificateFilename = $storedCertificate['storedFilename'];
        $settings->privateKeyFilename = $storedPrivateKey['storedFilename'];
        $settings->isSelfSigned = true;
        $settings->updatedAt = new DateTimeImmutable();
        $this->repository->save($settings);

        return $generated;
    }
}
