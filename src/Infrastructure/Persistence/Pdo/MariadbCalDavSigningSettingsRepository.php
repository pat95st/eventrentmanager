<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Settings\CalDavSigningSettings;
use App\Domain\Settings\CalDavSigningSettingsRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbCalDavSigningSettingsRepository implements CalDavSigningSettingsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(): CalDavSigningSettings
    {
        $stmt = $this->pdo->query('SELECT * FROM caldav_signing_settings WHERE id = 1');
        $row = $stmt->fetch();

        if ($row === false) {
            return new CalDavSigningSettings(null, null, false, null);
        }

        return new CalDavSigningSettings(
            certificateFilename: $row['certificate_filename'],
            privateKeyFilename: $row['private_key_filename'],
            isSelfSigned: (bool) $row['is_self_signed'],
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }

    public function save(CalDavSigningSettings $settings): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE caldav_signing_settings
             SET certificate_filename = :certificate_filename,
                 private_key_filename = :private_key_filename,
                 is_self_signed = :is_self_signed
             WHERE id = 1'
        );

        $stmt->execute([
            'certificate_filename' => $settings->certificateFilename,
            'private_key_filename' => $settings->privateKeyFilename,
            'is_self_signed' => $settings->isSelfSigned ? 1 : 0,
        ]);
    }
}
