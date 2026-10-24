<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Settings;

use App\Application\Settings\Command\UploadCalDavSigningCertificate;
use App\Application\Settings\Command\UploadCalDavSigningCertificateHandler;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Infrastructure\Http\CalDav\CalDavSelfSignedCertificateGenerator;
use Codeception\Test\Unit;
use Tests\Support\Fakes\InMemoryCalDavSigningSettingsRepository;

/**
 * Deckt bewusst nur den VALIDIERUNGS-Teil des Handlers ab (fehlende
 * Dateien, zu groß, ungültiges/nicht zusammenpassendes Zertifikat/
 * Privatschlüssel) — dieser Teil ist reine Logik und läuft vollständig ohne
 * echten HTTP-Upload. Der Erfolgspfad braucht move_uploaded_file() (siehe
 * LocalFileStorage::storeUploadedFile()), das außerhalb eines echten
 * HTTP-Requests immer fehlschlägt; der volle Upload-Erfolgspfad wird daher
 * stattdessen im Acceptance-Test CalendarSettingsCest über einen echten
 * HTTP-Multipart-Upload abgedeckt.
 */
final class UploadCalDavSigningCertificateHandlerTest extends Unit
{
    private UploadCalDavSigningCertificateHandler $handler;

    protected function _before(): void
    {
        $this->handler = new UploadCalDavSigningCertificateHandler(
            new InMemoryCalDavSigningSettingsRepository(),
            new LocalFileStorage(sys_get_temp_dir() . '/materialverleih-tests-' . uniqid('', true))
        );
    }

    public function testRejectsWhenNoCertificateWasProvided(): void
    {
        try {
            $this->handler->handle(new UploadCalDavSigningCertificate(
                certificateFile: null,
                privateKeyFile: $this->uploadedFile('irrelevant'),
            ));
            $this->fail('Erwartete ValidationException wurde nicht geworfen.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('certificate', $e->errors());
        }
    }

    public function testRejectsWhenNoPrivateKeyWasProvided(): void
    {
        try {
            $this->handler->handle(new UploadCalDavSigningCertificate(
                certificateFile: $this->uploadedFile('irrelevant'),
                privateKeyFile: null,
            ));
            $this->fail('Erwartete ValidationException wurde nicht geworfen.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('private_key', $e->errors());
        }
    }

    public function testRejectsAnInvalidCertificate(): void
    {
        $pair = $this->generateKeyPair();

        try {
            $this->handler->handle(new UploadCalDavSigningCertificate(
                certificateFile: $this->uploadedFile('Das ist kein PEM-Zertifikat.'),
                privateKeyFile: $this->uploadedFile($pair['key']),
            ));
            $this->fail('Erwartete ValidationException wurde nicht geworfen.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('certificate', $e->errors());
        }
    }

    public function testRejectsAMismatchedCertificateAndPrivateKey(): void
    {
        $pairA = $this->generateKeyPair();
        $pairB = $this->generateKeyPair();

        try {
            $this->handler->handle(new UploadCalDavSigningCertificate(
                certificateFile: $this->uploadedFile($pairA['cert']),
                privateKeyFile: $this->uploadedFile($pairB['key']),
            ));
            $this->fail('Erwartete ValidationException wurde nicht geworfen.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('private_key', $e->errors());
        }
    }

    /** @return array{cert: string, key: string} */
    private function generateKeyPair(): array
    {
        $pair = CalDavSelfSignedCertificateGenerator::generate('Unit-Test');
        $this->assertNotNull($pair, 'Die openssl-Erweiterung wird für diesen Test benötigt.');

        return $pair;
    }

    /** @return array{name: string, type: string, tmp_name: string, error: int, size: int} */
    private function uploadedFile(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'mv_test_upload_');
        file_put_contents($path, $content);

        return [
            'name' => 'upload.pem',
            'type' => 'application/x-pem-file',
            'tmp_name' => $path,
            'error' => UPLOAD_ERR_OK,
            'size' => strlen($content),
        ];
    }
}
