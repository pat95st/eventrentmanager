<?php

declare(strict_types=1);

namespace App\Application\Api\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Api\ApiToken;
use App\Domain\Api\ApiTokenRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

final class CreateApiTokenHandler implements CommandHandlerInterface
{
    private const TOKEN_BYTES = 32;
    // Erkennbares Präfix (vgl. Stripe/GitHub) — erleichtert das Wiedererkennen von
    // versehentlich commiteten/geloggten Tokens, ohne die Entropie zu verringern.
    private const TOKEN_PREFIX = 'mv_';

    public function __construct(private readonly ApiTokenRepositoryInterface $repository)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof CreateApiToken);

        $input = ApiTokenInput::validate($command->name, $command->ipAllowlist, $command->scopes, $command->rateLimitPerMinute);

        $rawToken = self::TOKEN_PREFIX . bin2hex(random_bytes(self::TOKEN_BYTES));
        $id = Uuid::v4();

        $token = new ApiToken(
            id: $id,
            name: $input['name'],
            tokenHash: hash('sha256', $rawToken),
            tokenLastFour: substr($rawToken, -4),
            ipAllowlist: $input['ipAllowlist'],
            enabled: true,
            lastUsedAt: null,
            createdAt: new DateTimeImmutable(),
            scopes: $input['scopes'],
            readOnly: $command->readOnly,
            rateLimitPerMinute: $input['rateLimit'],
            mcpAllowed: $command->mcpAllowed,
        );

        $this->repository->save($token);

        return new CreatedApiToken(id: $id, rawToken: $rawToken);
    }
}
