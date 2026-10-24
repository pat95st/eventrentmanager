<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandInterface;

final class UploadCalDavSigningCertificate implements CommandInterface
{
    /**
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int}|null $certificateFile PEM-Zertifikat (nur das Blatt-Zertifikat, keine Kette)
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int}|null $privateKeyFile PEM-Privatschlüssel, unverschlüsselt (ohne Passphrase)
     */
    public function __construct(
        public readonly ?array $certificateFile,
        public readonly ?array $privateKeyFile,
    ) {
    }
}
