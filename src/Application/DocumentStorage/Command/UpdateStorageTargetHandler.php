<?php

declare(strict_types=1);

namespace App\Application\DocumentStorage\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\DocumentStorage\DocumentStorageTargetRepositoryInterface;
use DateTimeImmutable;

final class UpdateStorageTargetHandler implements CommandHandlerInterface
{
    use ValidatesStorageTargetInput;

    public function __construct(private readonly DocumentStorageTargetRepositoryInterface $targets)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateStorageTarget);

        $target = $this->targets->findById($command->id);
        if ($target === null) {
            throw new NotFoundException("Ziellaufwerk \"{$command->id}\" wurde nicht gefunden.");
        }

        [$errors, $data] = $this->validateStorageTargetInput(
            $command->name,
            $command->protocol,
            $command->host,
            $command->port,
            $command->username,
            $command->password,
            $command->privateKey,
            $command->privateKeyPassphrase,
            $command->basePath,
            $command->webdavUseHttps,
            $command->webdavAllowSelfSigned,
            $command->s3AccessKeyId,
            $command->s3SecretAccessKey,
            $command->s3Bucket,
            $command->s3Region,
            $command->s3Endpoint,
            $command->s3UsePathStyle,
            $command->enabled,
        );

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $target->name = $data['name'];
        $target->protocol = $data['protocol'];
        $target->host = $data['host'];
        $target->port = $data['port'];
        $target->username = $data['username'];
        // Ein leeres Passwort-/Schlüsselfeld im Formular bedeutet
        // "unverändert lassen" (das Formular zeigt bestehende Zugangsdaten
        // aus Sicherheitsgründen nicht im Klartext an) — nur bei
        // tatsächlicher Eingabe wird der gespeicherte Wert überschrieben.
        if ($data['password'] !== null) {
            $target->password = $data['password'];
        }
        if ($data['privateKey'] !== null) {
            $target->privateKey = $data['privateKey'];
            $target->privateKeyPassphrase = $data['privateKeyPassphrase'];
        }
        if ($data['s3SecretAccessKey'] !== null) {
            $target->s3SecretAccessKey = $data['s3SecretAccessKey'];
        }
        $target->basePath = $data['basePath'];
        $target->webdavUseHttps = $data['webdavUseHttps'];
        $target->webdavAllowSelfSigned = $data['webdavAllowSelfSigned'];
        $target->s3AccessKeyId = $data['s3AccessKeyId'];
        $target->s3Bucket = $data['s3Bucket'];
        $target->s3Region = $data['s3Region'];
        $target->s3Endpoint = $data['s3Endpoint'];
        $target->s3UsePathStyle = $data['s3UsePathStyle'];
        $target->enabled = $data['enabled'];
        $target->updatedAt = new DateTimeImmutable();

        $this->targets->save($target);

        return null;
    }
}
