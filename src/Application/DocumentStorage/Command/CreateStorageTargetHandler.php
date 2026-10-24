<?php

declare(strict_types=1);

namespace App\Application\DocumentStorage\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\DocumentStorage\DocumentStorageTarget;
use App\Domain\DocumentStorage\DocumentStorageTargetRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

final class CreateStorageTargetHandler implements CommandHandlerInterface
{
    use ValidatesStorageTargetInput;

    public function __construct(private readonly DocumentStorageTargetRepositoryInterface $targets)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof CreateStorageTarget);

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

        $now = new DateTimeImmutable();
        $target = new DocumentStorageTarget(
            id: Uuid::v4(),
            name: $data['name'],
            protocol: $data['protocol'],
            host: $data['host'],
            port: $data['port'],
            username: $data['username'],
            password: $data['password'],
            privateKey: $data['privateKey'],
            privateKeyPassphrase: $data['privateKeyPassphrase'],
            basePath: $data['basePath'],
            webdavUseHttps: $data['webdavUseHttps'],
            webdavAllowSelfSigned: $data['webdavAllowSelfSigned'],
            s3AccessKeyId: $data['s3AccessKeyId'],
            s3SecretAccessKey: $data['s3SecretAccessKey'],
            s3Bucket: $data['s3Bucket'],
            s3Region: $data['s3Region'],
            s3Endpoint: $data['s3Endpoint'],
            s3UsePathStyle: $data['s3UsePathStyle'],
            enabled: $data['enabled'],
            createdAt: $now,
            updatedAt: $now,
        );

        $this->targets->save($target);

        return $target->id;
    }
}
