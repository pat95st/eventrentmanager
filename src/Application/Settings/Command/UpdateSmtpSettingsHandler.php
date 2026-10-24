<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Mail\MailCategory;
use App\Domain\Settings\SmtpEncryption;
use App\Domain\Settings\SmtpSettings;
use App\Domain\Settings\SmtpSettingsRepositoryInterface;

final class UpdateSmtpSettingsHandler implements CommandHandlerInterface
{
    public function __construct(private readonly SmtpSettingsRepositoryInterface $settingsRepository)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateSmtpSettings);

        $errors = [];

        $host = trim($command->host);
        if ($host === '') {
            $errors['host'] = 'Bitte einen SMTP-Server angeben.';
        }

        $port = ctype_digit($command->port) ? (int) $command->port : 0;
        if ($port < 1 || $port > 65535) {
            $errors['port'] = 'Bitte einen gültigen Port (1–65535) angeben.';
        }

        $encryption = SmtpEncryption::tryFrom($command->encryption);
        if ($encryption === null) {
            $errors['encryption'] = 'Bitte eine gültige Verschlüsselung wählen.';
        }

        $fromAddress = trim($command->fromAddress);
        if ($fromAddress === '' || filter_var($fromAddress, FILTER_VALIDATE_EMAIL) === false) {
            $errors['fromAddress'] = 'Bitte eine gültige Absender-E-Mail-Adresse angeben.';
        }

        $fromName = trim($command->fromName);
        if ($fromName === '') {
            $errors['fromName'] = 'Bitte einen Absendernamen angeben.';
        }

        $replyToAddress = $this->nullIfEmpty($command->replyToAddress);
        if ($replyToAddress !== null && filter_var($replyToAddress, FILTER_VALIDATE_EMAIL) === false) {
            $errors['replyToAddress'] = 'Bitte eine gültige Reply-To-Adresse angeben.';
        }

        $archiveBccAddress = $this->nullIfEmpty($command->archiveBccAddress);
        if ($archiveBccAddress !== null && filter_var($archiveBccAddress, FILTER_VALIDATE_EMAIL) === false) {
            $errors['archiveBccAddress'] = 'Bitte eine gültige Archivierungsadresse angeben.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $current = $this->settingsRepository->get();

        // Unbekannte/ungültige Werte werden stillschweigend verworfen (kein
        // Fehler) — ein veralteter/manipulierter Kategoriewert soll das
        // Speichern der übrigen Einstellungen nicht blockieren. Bleibt nach
        // dem Filtern nichts übrig, wird bewusst [] gespeichert ("keine
        // Kategorie archivieren"), NICHT null ("alle") — siehe
        // UpdateSmtpSettings::$archiveCategories.
        $archiveCategories = array_values(array_filter(
            array_map(static fn (string $value) => MailCategory::tryFrom($value)?->value, $command->archiveCategories)
        ));

        $settings = new SmtpSettings(
            host: $host,
            port: $port,
            encryption: $encryption,
            username: $this->nullIfEmpty($command->username),
            // Leeres Passwortfeld im Formular = Passwort unverändert lassen,
            // damit das gespeicherte Passwort nicht bei jeder Bearbeitung
            // erneut im Klartext im Formular stehen muss.
            password: $command->password !== null && $command->password !== '' ? $command->password : $current->password,
            fromAddress: $fromAddress,
            fromName: $fromName,
            replyToAddress: $replyToAddress,
            archiveBccAddress: $archiveBccAddress,
            archiveCategories: $archiveCategories,
        );

        $this->settingsRepository->save($settings);

        return null;
    }

    private function nullIfEmpty(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
    }
}
