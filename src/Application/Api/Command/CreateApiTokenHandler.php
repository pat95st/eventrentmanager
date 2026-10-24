<?php

declare(strict_types=1);

namespace App\Application\Api\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Api\ApiToken;
use App\Domain\Api\ApiTokenRepositoryInterface;
use App\Domain\Api\IpAllowlist;
use App\Domain\Exception\ValidationException;
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

        $name = trim((string) $command->name);
        if ($name === '') {
            throw new ValidationException(['name' => 'Bitte einen Namen für das Token angeben (z. B. "Website-Integration").']);
        }

        $ipAllowlist = trim((string) $command->ipAllowlist);
        if ($ipAllowlist !== '') {
            $error = IpAllowlist::validationError($ipAllowlist);
            if ($error !== null) {
                throw new ValidationException(['ipAllowlist' => $error]);
            }
        }

        $rawToken = self::TOKEN_PREFIX . bin2hex(random_bytes(self::TOKEN_BYTES));
        $id = Uuid::v4();

        $token = new ApiToken(
            id: $id,
            name: $name,
            tokenHash: hash('sha256', $rawToken),
            tokenLastFour: substr($rawToken, -4),
            ipAllowlist: $ipAllowlist !== '' ? $ipAllowlist : null,
            enabled: true,
            lastUsedAt: null,
            createdAt: new DateTimeImmutable(),
        );

        $this->repository->save($token);

        return new CreatedApiToken(id: $id, rawToken: $rawToken);
    }
}
