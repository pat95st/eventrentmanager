<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\CalDav;

/**
 * Erzeugt ein selbstsigniertes Zertifikat als Fallback für die Signatur
 * heruntergeladener .mobileconfig-Profile, wenn kein echtes Zertifikat
 * hochgeladen wurde (siehe CalDavSigningCertificateProvider). Ein
 * selbstsigniertes Zertifikat macht das Profil in iOS/macOS nicht
 * "vertrauenswürdig" (die Geräte zeigen weiterhin eine
 * Signatur-Warnung), verbessert aber gegenüber einem unsignierten Profil
 * zumindest die Integritätsprüfung (Änderungen am Profil nach dem
 * Download werden erkennbar).
 */
final class CalDavSelfSignedCertificateGenerator
{
    private const VALIDITY_DAYS = 3650; // 10 Jahre

    /** @return array{cert: string, key: string}|null null, wenn die openssl-Erweiterung fehlt oder die Erzeugung fehlschlägt */
    public static function generate(string $commonName): ?array
    {
        if (!extension_loaded('openssl')) {
            return null;
        }

        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if ($privateKey === false) {
            return null;
        }

        $csr = openssl_csr_new(
            ['commonName' => $commonName, 'organizationName' => $commonName],
            $privateKey,
            ['digest_alg' => 'sha256']
        );
        if ($csr === false) {
            return null;
        }

        $certificate = openssl_csr_sign($csr, null, $privateKey, self::VALIDITY_DAYS, ['digest_alg' => 'sha256']);
        if ($certificate === false) {
            return null;
        }

        $certExported = openssl_x509_export($certificate, $certPem);
        $keyExported = openssl_pkey_export($privateKey, $keyPem);

        if (!$certExported || !$keyExported) {
            return null;
        }

        return ['cert' => $certPem, 'key' => $keyPem];
    }
}
