<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Settings\SmtpEncryption;
use App\Domain\Settings\SmtpSettings;
use App\Domain\Settings\SmtpSettingsRepositoryInterface;
use PDO;
use RuntimeException;

final class MariadbSmtpSettingsRepository implements SmtpSettingsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(): SmtpSettings
    {
        $stmt = $this->pdo->query('SELECT * FROM smtp_settings WHERE id = 1');
        $row = $stmt->fetch();

        if ($row === false) {
            // Sollte durch die Migration/das Schema nie vorkommen (die
            // Singleton-Zeile wird dort direkt mit angelegt), aber ein
            // klarer Fehler ist hier besser als eine stille Falscheinstellung.
            throw new RuntimeException('SMTP-Einstellungen wurden nicht gefunden — fehlt die Migration 008?');
        }

        return new SmtpSettings(
            host: $row['host'],
            port: (int) $row['port'],
            encryption: SmtpEncryption::from($row['encryption']),
            username: $row['username'],
            password: $row['password'],
            fromAddress: $row['from_address'],
            fromName: $row['from_name'],
            replyToAddress: $row['reply_to_address'],
            archiveBccAddress: $row['archive_bcc_address'],
            archiveCategories: $this->parseArchiveCategories($row['archive_categories']),
        );
    }

    public function save(SmtpSettings $settings): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE smtp_settings SET
                host = :host, port = :port, encryption = :encryption, username = :username,
                password = :password, from_address = :from_address, from_name = :from_name,
                reply_to_address = :reply_to_address, archive_bcc_address = :archive_bcc_address,
                archive_categories = :archive_categories
             WHERE id = 1'
        );

        $stmt->execute([
            'host' => $settings->host,
            'port' => $settings->port,
            'encryption' => $settings->encryption->value,
            'username' => $settings->username,
            'password' => $settings->password,
            'from_address' => $settings->fromAddress,
            'from_name' => $settings->fromName,
            'reply_to_address' => $settings->replyToAddress,
            'archive_bcc_address' => $settings->archiveBccAddress,
            'archive_categories' => $settings->archiveCategories !== null ? implode(',', $settings->archiveCategories) : null,
        ]);
    }

    /**
     * Wandelt die kommagetrennte Spalte in ein Array von
     * MailCategory-Werten um. NULL oder eine leere Spalte bedeuten "alle
     * Kategorien" (siehe SmtpSettings::shouldArchive()) und werden daher
     * als null zurückgegeben, nicht als leeres Array.
     *
     * @return string[]|null
     */
    private function parseArchiveCategories(?string $raw): ?array
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $values = array_filter(array_map('trim', explode(',', $raw)), static fn (string $value) => $value !== '');

        return array_values($values);
    }
}
