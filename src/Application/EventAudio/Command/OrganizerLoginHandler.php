<?php

declare(strict_types=1);

namespace App\Application\EventAudio\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\EventAudio\OrganizerLogin;
use App\Domain\EventAudio\OrganizerLoginRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Veranstalter-Zugänge zur Live-Oberfläche (CreateOrganizerLogin,
 * UpdateOrganizerLogin, DeleteOrganizerLogin, AuthenticateOrganizer).
 * Nach 5 Fehlversuchen wird der Zugang 15 Minuten gesperrt.
 */
final class OrganizerLoginHandler implements CommandHandlerInterface
{
    public function __construct(private readonly OrganizerLoginRepositoryInterface $logins)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        return match (true) {
            $command instanceof CreateOrganizerLogin => $this->create($command),
            $command instanceof UpdateOrganizerLogin => $this->update($command),
            $command instanceof DeleteOrganizerLogin => $this->delete($command),
            $command instanceof AuthenticateOrganizer => $this->authenticate($command),
            default => throw new \LogicException('Unbekannter Befehl ' . $command::class),
        };
    }

    /** @return array{id: string, username: string, password: string} */
    private function create(CreateOrganizerLogin $command): array
    {
        $errors = [];
        $label = trim($command->label);
        if ($label === '') {
            $label = 'Veranstalter';
        }
        $username = mb_strtolower(trim($command->username));
        if (!preg_match('/^[a-z0-9._-]{3,80}$/', $username)) {
            $errors['username'] = 'Benutzername: 3–80 Zeichen, nur Kleinbuchstaben, Ziffern, Punkt, Binde- und Unterstrich.';
        } elseif ($this->logins->findByUsername($username) !== null) {
            $errors['username'] = 'Dieser Benutzername ist bereits vergeben.';
        }
        $password = $command->password !== null && trim($command->password) !== '' ? $command->password : OrganizerLogin::generatePassword();
        if (mb_strlen($password) < 8) {
            $errors['password'] = 'Das Passwort muss mindestens 8 Zeichen lang sein.';
        }
        [$from, $until] = $this->parseRange($command->validFrom, $command->validUntil, $errors);
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $login = new OrganizerLogin(
            id: Uuid::v4(),
            rentalId: $command->rentalId,
            label: mb_substr($label, 0, 120),
            username: $username,
            passwordHash: password_hash($password, PASSWORD_DEFAULT),
            validFrom: $from,
            validUntil: $until,
            enabled: true,
            failedAttempts: 0,
            lockedUntil: null,
            lastLoginAt: null,
            createdAt: new DateTimeImmutable(),
        );
        $this->logins->save($login);

        return ['id' => $login->id, 'username' => $username, 'password' => $password];
    }

    /** @return string|null neues Passwort, wenn zurückgesetzt */
    private function update(UpdateOrganizerLogin $command): ?string
    {
        $login = $this->logins->findById($command->id);
        if ($login === null || $login->rentalId !== $command->rentalId) {
            throw new NotFoundException('Zugang nicht gefunden.');
        }
        $errors = [];
        [$from, $until] = $this->parseRange($command->validFrom, $command->validUntil, $errors);
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $label = trim($command->label);
        $login->label = $label !== '' ? mb_substr($label, 0, 120) : $login->label;
        $login->validFrom = $from;
        $login->validUntil = $until;
        $login->enabled = $command->enabled;
        $login->failedAttempts = 0;
        $login->lockedUntil = null;
        $newPassword = null;
        if ($command->resetPassword) {
            $newPassword = OrganizerLogin::generatePassword();
            $login->passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        }
        $this->logins->save($login);

        return $newPassword;
    }

    private function delete(DeleteOrganizerLogin $command): null
    {
        $login = $this->logins->findById($command->id);
        if ($login === null || $login->rentalId !== $command->rentalId) {
            throw new NotFoundException('Zugang nicht gefunden.');
        }
        $this->logins->delete($login->id);

        return null;
    }

    private function authenticate(AuthenticateOrganizer $command): OrganizerLogin
    {
        $generic = new ValidationException(['login' => 'Benutzername oder Passwort ist falsch, oder der Zugang ist (noch) nicht gültig.']);
        $now = new DateTimeImmutable();
        $login = $this->logins->findByUsername(mb_strtolower(trim($command->username)));
        if ($login === null) {
            static $dummyHash = null;
            $dummyHash ??= password_hash('timing-dummy', PASSWORD_DEFAULT);
            password_verify($command->password, $dummyHash); // annähernd gleiche Laufzeit wie bei existierendem Benutzer

            throw $generic;
        }
        if ($login->isLocked($now)) {
            throw new ValidationException(['login' => 'Zu viele Fehlversuche. Bitte in ' . OrganizerLogin::LOCK_MINUTES . ' Minuten erneut versuchen.']);
        }
        if (!password_verify($command->password, $login->passwordHash)) {
            $login->failedAttempts++;
            if ($login->failedAttempts >= OrganizerLogin::MAX_FAILED_ATTEMPTS) {
                $login->lockedUntil = $now->modify('+' . OrganizerLogin::LOCK_MINUTES . ' minutes');
                $login->failedAttempts = 0;
            }
            $this->logins->save($login);

            throw $generic;
        }
        if (!$login->isValidAt($now)) {
            throw $generic;
        }
        $login->failedAttempts = 0;
        $login->lockedUntil = null;
        $login->lastLoginAt = $now;
        if (password_needs_rehash($login->passwordHash, PASSWORD_DEFAULT)) {
            $login->passwordHash = password_hash($command->password, PASSWORD_DEFAULT);
        }
        $this->logins->save($login);

        return $login;
    }

    /**
     * @param array<string, string> $errors
     * @return array{0: ?DateTimeImmutable, 1: ?DateTimeImmutable}
     */
    private function parseRange(?string $from, ?string $until, array &$errors): array
    {
        $parse = static function (?string $v): DateTimeImmutable|false|null {
            $v = trim((string) $v);
            if ($v === '') {
                return null;
            }

            return DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $v) ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $v) ?: false;
        };
        $f = $parse($from);
        $u = $parse($until);
        if ($f === false) {
            $errors['valid_from'] = 'Ungültiges Datum „gültig ab“.';
        }
        if ($u === false) {
            $errors['valid_until'] = 'Ungültiges Datum „gültig bis“.';
        }
        if ($f instanceof DateTimeImmutable && $u instanceof DateTimeImmutable && $u <= $f) {
            $errors['valid_until'] = '„Gültig bis“ muss nach „gültig ab“ liegen.';
        }

        return [$f ?: null, $u ?: null];
    }
}
