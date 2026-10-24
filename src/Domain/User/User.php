<?php

declare(strict_types=1);

namespace App\Domain\User;

use DateTimeImmutable;

final class User
{
    /**
     * @param Permission[] $permissions Explizit zugewiesene Rechte (ohne
     *        Administration-Implikation aufgelöst, siehe has()).
     */
    public function __construct(
        public readonly string $id,
        public string $email,
        // NULL = kein Passwort gesetzt (per E-Mail-Einladung angelegter
        // Benutzer, muss es zunächst über einen Setup-Link festlegen, siehe
        // App\Application\Auth\PasswordSetupTokenIssuer). Jede
        // password_verify()-Anmeldung gegen einen NULL-Hash scheitert
        // kontrolliert, siehe AuthenticatePasswordHandler.
        public ?string $passwordHash,
        // Vorname/Nachname: nullable, da bestehende/legacy Benutzer sie noch
        // nicht gesetzt haben; das Anlage-Formular verlangt sie künftig als
        // Pflichtfeld (siehe CreateUserHandler), das Bearbeiten-Formular
        // lässt sie optional.
        public ?string $firstName,
        public ?string $lastName,
        public array $permissions,
        public TwoFactorMethod $twoFactorMethod,
        public ?string $totpSecret,
        public bool $mustChangePassword,
        // Erzwingt (analog mustChangePassword) das Einrichten eines zweiten
        // Faktors beim ersten Login, siehe App\Infrastructure\Http\Auth\AuthGuard.
        // Wird ignoriert, sobald twoFactorMethod !== None ist.
        public bool $mustSetupTwoFactor,
        public bool $isActive,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        // Standard-Seitengröße für paginierte Listen (Artikel,
        // Veranstaltungen, Kunden, ...), siehe App\Infrastructure\Http\
        // Pagination und /profil/darstellung.
        public int $defaultPageSize = 25,
        // Sprachpräferenz der Oberfläche ('de'/'en'), siehe
        // App\Infrastructure\Http\Translator und /profil/darstellung.
        public string $locale = 'de',
        // Ob dieser Benutzer per E-Mail benachrichtigt werden möchte, wenn
        // der Beleg-Posteingang-Cronjob neue Nachrichten eingesammelt hat
        // (nur wirksam für Administratoren, siehe
        // ImportReceiptEmailsHandler::notifyAdministrators()), einstellbar
        // unter /profil/darstellung.
        public bool $notifyOnNewReceiptEmail = false,
    ) {
    }

    /**
     * Prüft ein Recht inkl. der Administration-Implikation: ein Benutzer mit
     * dem Recht "Administration" hat automatisch auch alle anderen Rechte.
     */
    public function has(Permission $permission): bool
    {
        if (in_array(Permission::Administration, $this->permissions, true)) {
            return true;
        }

        return in_array($permission, $this->permissions, true);
    }

    public function isAdministrator(): bool
    {
        return in_array(Permission::Administration, $this->permissions, true);
    }

    /** "Vorname Nachname", oder die E-Mail-Adresse als Fallback (legacy/seed-Benutzer ohne Namen). */
    public function displayName(): string
    {
        $name = trim(($this->firstName ?? '') . ' ' . ($this->lastName ?? ''));

        return $name !== '' ? $name : $this->email;
    }
}
