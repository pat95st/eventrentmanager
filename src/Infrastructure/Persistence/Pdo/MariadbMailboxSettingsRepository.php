<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Settings\MailboxEncryption;
use App\Domain\Settings\MailboxProtocol;
use App\Domain\Settings\MailboxSettings;
use App\Domain\Settings\MailboxSettingsRepositoryInterface;
use PDO;
use RuntimeException;

final class MariadbMailboxSettingsRepository implements MailboxSettingsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(): MailboxSettings
    {
        $stmt = $this->pdo->query('SELECT * FROM mailbox_settings WHERE id = 1');
        $row = $stmt->fetch();

        if ($row === false) {
            throw new RuntimeException('Mailbox-Einstellungen wurden nicht gefunden — fehlt die Migration 039?');
        }

        return new MailboxSettings(
            protocol: MailboxProtocol::from($row['protocol']),
            host: $row['host'],
            port: $row['port'] !== null ? (int) $row['port'] : null,
            encryption: MailboxEncryption::from($row['encryption']),
            username: $row['username'],
            password: $row['password'],
            imapFolder: $row['imap_folder'],
            enabled: (bool) $row['enabled'],
        );
    }

    public function save(MailboxSettings $settings): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE mailbox_settings SET
                protocol = :protocol, host = :host, port = :port, encryption = :encryption,
                username = :username, password = :password, imap_folder = :imap_folder, enabled = :enabled
             WHERE id = 1'
        );

        $stmt->execute([
            'protocol' => $settings->protocol->value,
            'host' => $settings->host,
            'port' => $settings->port,
            'encryption' => $settings->encryption->value,
            'username' => $settings->username,
            'password' => $settings->password,
            'imap_folder' => $settings->imapFolder,
            'enabled' => $settings->enabled ? 1 : 0,
        ]);
    }
}
