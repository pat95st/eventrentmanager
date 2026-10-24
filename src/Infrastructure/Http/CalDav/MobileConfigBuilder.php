<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\CalDav;

use App\Shared\Uuid;

/**
 * Baut ein Apple-Konfigurationsprofil (.mobileconfig) zur automatischen
 * Einrichtung EINES CalDAV-Konto in iOS/iPadOS/macOS. Enthält absichtlich
 * nur einen "com.apple.CalDAV.account"-Payload mit
 * CalDAVAccountAutoDiscover = true: das Gerät entdeckt darüber selbst ALLE
 * für den User freigegebenen Kalender über den normalen CalDAV-
 * Discovery-Ablauf (.well-known/caldav → current-user-principal →
 * calendar-home-set, siehe CalDavController) — es müssen hier keine
 * einzelnen Kalender-URLs aufgezählt werden.
 *
 * Wird, falls ein Zertifikat verfügbar ist (siehe
 * CalDavSigningCertificateProvider), als PKCS#7/CMS SignedData signiert:
 * die Nutzdaten (Property-List) werden dafür per openssl_pkcs7_sign() in
 * ein S/MIME-Nachrichtenformat gepackt (PHPs einzige eingebaute
 * PKCS7-Signierfunktion), wovon nur der Base64-Body hinter der Leerzeile
 * benötigt wird — dessen Dekodierung ist exakt der rohe DER-kodierte
 * PKCS#7-Blob, den ein signiertes .mobileconfig als Dateiinhalt erwartet
 * (kein Aufruf einer externen openssl-Kommandozeile nötig).
 */
final class MobileConfigBuilder
{
    public function __construct(private readonly CalDavSigningCertificateProvider $certificateProvider)
    {
    }

    public function build(
        string $organizationName,
        string $accountDescription,
        string $host,
        int $port,
        bool $useSsl,
        string $username,
        string $password,
    ): string {
        $plist = $this->buildPlist($organizationName, $accountDescription, $host, $port, $useSsl, $username, $password);

        $certificate = $this->certificateProvider->get();
        if ($certificate === null) {
            return $plist;
        }

        return $this->sign($plist, $certificate['cert'], $certificate['key']) ?? $plist;
    }

    private function buildPlist(
        string $organizationName,
        string $accountDescription,
        string $host,
        int $port,
        bool $useSsl,
        string $username,
        string $password,
    ): string {
        $accountUuid = Uuid::v4();
        $profileUuid = Uuid::v4();

        return <<<PLIST
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>PayloadContent</key>
    <array>
        <dict>
            <key>CalDAVAccountAutoDiscover</key>
            <true/>
            <key>CalDAVAccountDescription</key>
            <string>{$this->esc($accountDescription)}</string>
            <key>CalDAVHostName</key>
            <string>{$this->esc($host)}</string>
            <key>CalDAVPort</key>
            <integer>{$port}</integer>
            <key>CalDAVUseSSL</key>
            <{$this->bool($useSsl)}/>
            <key>CalDAVUsername</key>
            <string>{$this->esc($username)}</string>
            <key>CalDAVPassword</key>
            <string>{$this->esc($password)}</string>
            <key>PayloadDescription</key>
            <string>CalDAV-Konto</string>
            <key>PayloadDisplayName</key>
            <string>CalDAV</string>
            <key>PayloadIdentifier</key>
            <string>de.materialverleih.caldav.account.{$accountUuid}</string>
            <key>PayloadType</key>
            <string>com.apple.CalDAV.account</string>
            <key>PayloadUUID</key>
            <string>{$accountUuid}</string>
            <key>PayloadVersion</key>
            <integer>1</integer>
        </dict>
    </array>
    <key>PayloadDescription</key>
    <string>{$this->esc('Richtet den CalDAV-Kalenderzugriff für ' . $organizationName . ' ein.')}</string>
    <key>PayloadDisplayName</key>
    <string>{$this->esc($accountDescription)}</string>
    <key>PayloadIdentifier</key>
    <string>de.materialverleih.caldav.profile.{$profileUuid}</string>
    <key>PayloadOrganization</key>
    <string>{$this->esc($organizationName)}</string>
    <key>PayloadRemovalDisallowed</key>
    <false/>
    <key>PayloadType</key>
    <string>Configuration</string>
    <key>PayloadUUID</key>
    <string>{$profileUuid}</string>
    <key>PayloadVersion</key>
    <integer>1</integer>
</dict>
</plist>
PLIST;
    }

    private function sign(string $plist, string $certPem, string $keyPem): ?string
    {
        $tmpDir = sys_get_temp_dir();
        $plistFile = tempnam($tmpDir, 'mv_mc_plist_');
        $certFile = tempnam($tmpDir, 'mv_mc_cert_');
        $keyFile = tempnam($tmpDir, 'mv_mc_key_');
        $outFile = tempnam($tmpDir, 'mv_mc_signed_');

        if ($plistFile === false || $certFile === false || $keyFile === false || $outFile === false) {
            return null;
        }

        try {
            file_put_contents($plistFile, $plist);
            file_put_contents($certFile, $certPem);
            file_put_contents($keyFile, $keyPem);

            $signed = openssl_pkcs7_sign(
                $plistFile,
                $outFile,
                'file://' . $certFile,
                'file://' . $keyFile,
                [],
                PKCS7_BINARY | PKCS7_NOATTR
            );

            if (!$signed) {
                return null;
            }

            $smime = file_get_contents($outFile);
            if ($smime === false) {
                return null;
            }

            // S/MIME-Header (bis zur ersten Leerzeile) abtrennen — siehe
            // Klassendocblock.
            $separatorPosition = strpos($smime, "\r\n\r\n");
            if ($separatorPosition === false) {
                $separatorPosition = strpos($smime, "\n\n");
            }
            if ($separatorPosition === false) {
                return null;
            }

            $base64Body = str_replace(["\r", "\n"], '', substr($smime, $separatorPosition));
            $der = base64_decode($base64Body, true);

            return $der !== false ? $der : null;
        } finally {
            foreach ([$plistFile, $certFile, $keyFile, $outFile] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function bool(bool $value): string
    {
        return $value ? 'true' : 'false';
    }
}
