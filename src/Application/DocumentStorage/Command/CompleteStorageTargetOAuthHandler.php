<?php

declare(strict_types=1);

namespace App\Application\DocumentStorage\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\DocumentStorage\DocumentStorageTargetRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\DocumentStorage\Cloud\CloudOAuthClientInterface;
use App\Infrastructure\DocumentStorage\StorageUploadException;
use DateTimeImmutable;

final class CompleteStorageTargetOAuthHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly DocumentStorageTargetRepositoryInterface $targets,
        private readonly CloudOAuthClientInterface $oauth,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof CompleteStorageTargetOAuth);

        $target = $this->targets->findById($command->targetId);
        if ($target === null) {
            throw new NotFoundException("Ziellaufwerk \"{$command->targetId}\" wurde nicht gefunden.");
        }
        if (!$target->protocol->usesOAuth()) {
            throw new ValidationException(['oauth' => "{$target->protocol->label()} wird nicht per OAuth verbunden."]);
        }

        try {
            $tokens = $this->oauth->exchangeCode($target, $command->redirectUri, $command->code);
        } catch (StorageUploadException $e) {
            throw new ValidationException(['oauth' => $e->getMessage()]);
        }

        $target->oauthAccessToken = $tokens->accessToken;
        $target->oauthRefreshToken = $tokens->refreshToken;
        $target->oauthAccessTokenExpiresAt = $tokens->expiresAt;
        $target->oauthConnectedAt = new DateTimeImmutable();
        // Display only — a failed lookup does not affect the connection.
        $target->oauthAccountLabel = $this->oauth->accountLabel($target, $tokens->accessToken);
        $target->updatedAt = new DateTimeImmutable();

        $this->targets->save($target);

        return $target->oauthAccountLabel;
    }
}
