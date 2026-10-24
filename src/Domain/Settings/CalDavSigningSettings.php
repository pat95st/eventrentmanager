<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use DateTimeImmutable;

/**
 * Zertifikat, mit dem heruntergeladene .mobileconfig-Profile (CalDAV-
 * Konto-Einrichtung, siehe MobileConfigBuilder) signiert werden — entweder
 * ein vom Administrator hochgeladenes echtes Zertifikat, oder (Fallback,
 * automatisch erzeugt) ein selbstsigniertes (siehe
 * CalDavSigningCertificateProvider/CalDavSelfSignedCertificateGenerator).
 * Singleton-Zeile, analog zu ApiSettings/CompanySettings.
 */
final class CalDavSigningSettings
{
    public function __construct(
        public ?string $certificateFilename,
        public ?string $privateKeyFilename,
        public bool $isSelfSigned,
        public ?DateTimeImmutable $updatedAt,
    ) {
    }
}
