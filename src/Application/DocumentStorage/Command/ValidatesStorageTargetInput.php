<?php

declare(strict_types=1);

namespace App\Application\DocumentStorage\Command;

use App\Domain\DocumentStorage\StorageProtocol;

/** Gemeinsame Validierung für Create-/UpdateStorageTargetHandler. */
trait ValidatesStorageTargetInput
{
    /**
     * @return array{0: array<string, string>, 1: array<string, mixed>}
     */
    private function validateStorageTargetInput(
        string $name,
        string $protocol,
        string $host,
        string $port,
        string $username,
        ?string $password,
        ?string $privateKey,
        ?string $privateKeyPassphrase,
        string $basePath,
        bool $webdavUseHttps,
        bool $webdavAllowSelfSigned,
        string $s3AccessKeyId,
        ?string $s3SecretAccessKey,
        string $s3Bucket,
        string $s3Region,
        string $s3Endpoint,
        bool $s3UsePathStyle,
        bool $enabled,
    ): array {
        $errors = [];

        $name = trim($name);
        if ($name === '') {
            $errors['name'] = 'Bitte einen Namen angeben.';
        }

        $protocolEnum = StorageProtocol::tryFrom($protocol);
        if ($protocolEnum === null) {
            $errors['protocol'] = 'Bitte ein gültiges Protokoll wählen.';
        }

        $host = trim($host);
        $portValue = $port !== '' ? (int) $port : null;
        $username = trim($username);
        $basePath = trim($basePath);
        $s3AccessKeyId = trim($s3AccessKeyId);
        $s3Bucket = trim($s3Bucket);
        $s3Region = trim($s3Region) !== '' ? trim($s3Region) : 'eu-central-1';
        $s3Endpoint = trim($s3Endpoint);

        if ($protocolEnum === StorageProtocol::S3) {
            if ($s3AccessKeyId === '') {
                $errors['s3AccessKeyId'] = 'Bitte einen Access Key angeben.';
            }
            if ($s3Bucket === '') {
                $errors['s3Bucket'] = 'Bitte einen Bucket-Namen angeben.';
            }
        } elseif ($protocolEnum !== null) {
            if ($host === '') {
                $errors['host'] = 'Bitte einen Host angeben.';
            }
            if ($portValue !== null && ($portValue < 1 || $portValue > 65535)) {
                $errors['port'] = 'Bitte einen Port zwischen 1 und 65535 angeben.';
            }
            if (in_array($protocolEnum, [StorageProtocol::Sftp, StorageProtocol::Scp], true)) {
                $hasPassword = $password !== null && trim($password) !== '';
                $hasKey = $privateKey !== null && trim($privateKey) !== '';
                if (!$hasPassword && !$hasKey) {
                    $errors['password'] = 'Bitte entweder ein Passwort oder einen privaten Schlüssel angeben.';
                }
            }
        }

        return [$errors, [
            'name' => $name,
            'protocol' => $protocolEnum,
            'host' => $host !== '' ? $host : null,
            'port' => $portValue,
            'username' => $username !== '' ? $username : null,
            'password' => $password !== null && trim($password) !== '' ? $password : null,
            'privateKey' => $privateKey !== null && trim($privateKey) !== '' ? $privateKey : null,
            'privateKeyPassphrase' => $privateKeyPassphrase !== null && trim($privateKeyPassphrase) !== '' ? $privateKeyPassphrase : null,
            'basePath' => $basePath !== '' ? $basePath : null,
            'webdavUseHttps' => $webdavUseHttps,
            'webdavAllowSelfSigned' => $webdavAllowSelfSigned,
            's3AccessKeyId' => $s3AccessKeyId !== '' ? $s3AccessKeyId : null,
            's3SecretAccessKey' => $s3SecretAccessKey !== null && trim($s3SecretAccessKey) !== '' ? $s3SecretAccessKey : null,
            's3Bucket' => $s3Bucket !== '' ? $s3Bucket : null,
            's3Region' => $s3Region,
            's3Endpoint' => $s3Endpoint !== '' ? $s3Endpoint : null,
            's3UsePathStyle' => $s3UsePathStyle,
            'enabled' => $enabled,
        ]];
    }
}
