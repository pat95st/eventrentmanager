<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Auth\LoginChallengeRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\User\TwoFactorMethod;
use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;
use App\Shared\Totp;
use DateTimeImmutable;
use function App\Infrastructure\Http\t;

final class VerifyTwoFactorCodeHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly LoginChallengeRepositoryInterface $challenges,
    ) {
    }

    public function handle(CommandInterface $command): User
    {
        assert($command instanceof VerifyTwoFactorCode);

        $user = $this->users->findById($command->userId);
        if ($user === null) {
            throw new NotFoundException("Benutzer mit ID \"{$command->userId}\" wurde nicht gefunden.");
        }

        $code = trim($command->code);
        $valid = match ($user->twoFactorMethod) {
            TwoFactorMethod::Totp => $user->totpSecret !== null && Totp::verify($user->totpSecret, $code),
            TwoFactorMethod::Email => $this->verifyEmailCode($user->id, $code),
            TwoFactorMethod::None => false,
        };

        if (!$valid) {
            throw new ValidationException(['code' => t('auth.two_factor.error_invalid_code')]);
        }

        return $user;
    }

    private function verifyEmailCode(string $userId, string $code): bool
    {
        $challenge = $this->challenges->findLatestActiveForUser($userId);
        if ($challenge === null) {
            return false;
        }

        $now = new DateTimeImmutable();
        if ($challenge->isConsumed() || $challenge->isExpired($now)) {
            return false;
        }

        if (!password_verify($code, $challenge->codeHash)) {
            return false;
        }

        $challenge->consumedAt = $now;
        $this->challenges->save($challenge);

        return true;
    }
}
