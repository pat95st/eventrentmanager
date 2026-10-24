<?php

declare(strict_types=1);

namespace App\Infrastructure\WebAuthn;

/**
 * Enthält sämtliche kryptografischen/Byte-Format-Mechanik für Passkey-
 * (WebAuthn-)Registrierung und -Anmeldung: base64url-Kodierung,
 * Optionen-Aufbau für navigator.credentials.create()/get(), Parsen von
 * attestationObject/authenticatorData sowie Signaturprüfung per openssl.
 * Bewusst als eigenständige, CQRS-freie Infrastrukturklasse (keine
 * Command-/Query-Interfaces) — die Command-Handler in
 * App\Application\Auth\Command nutzen sie nur, analog zur Trennung
 * PDF-Writer/-Generator bei App\Infrastructure\Pdf.
 *
 * Es wird bewusst KEINE Attestation-Signatur/-Vertrauenskette gegen
 * Hersteller-Root-Zertifikate geprüft ("none"-Attestation-Annahme) — für
 * ein selbst gehostetes internes Tool, dem es nur um "ist das derselbe
 * Authenticator wie bei der Registrierung" (Authentifizierung) geht, nicht
 * um Geräte-Herkunftsnachweis, ist das eine verbreitete, akzeptierte
 * Vereinfachung. Für die eigentliche Sicherheit der spätere Anmeldung
 * zählt ausschließlich, dass der öffentliche Schlüssel korrekt aus
 * authenticatorData extrahiert wird — das wird sehr wohl geprüft.
 */
final class WebAuthnService
{
    private const CHALLENGE_BYTES = 32;
    private const TIMEOUT_MS = 60000;

    // COSE-Algorithmus-Identifikatoren (RFC 8152), wie von den
    // pubKeyCredParams der Registrierung angefordert.
    private const COSE_ALG_ES256 = -7;
    private const COSE_ALG_RS256 = -257;

    // COSE-Key-Type-Identifikatoren.
    private const COSE_KTY_EC2 = 2;
    private const COSE_KTY_RSA = 3;

    // COSE-EC2-Kurven-Identifikator für P-256 (secp256r1/prime256v1).
    private const COSE_CRV_P256 = 1;

    // authData-Flag-Bits (WebAuthn Level 2, §6.1).
    private const FLAG_USER_PRESENT = 0x01;
    private const FLAG_USER_VERIFIED = 0x04;
    private const FLAG_ATTESTED_CREDENTIAL_DATA = 0x40;

    // Feste DER-Präambel für eine SubjectPublicKeyInfo mit
    // id-ecPublicKey/prime256v1, bis inkl. des unkomprimierten
    // Punkt-Präfix-Bytes 0x04 — danach folgen nur noch die 32+32 Byte X/Y.
    private const EC_P256_DER_PREFIX = "\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x03\x42\x00\x04";

    public function generateChallenge(): string
    {
        return random_bytes(self::CHALLENGE_BYTES);
    }

    public function challengeTimeout(): int
    {
        return self::TIMEOUT_MS;
    }

    // --- base64url (RFC 4648 §5) — WebAuthn überträgt alle Binärfelder so. ---

    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $data): string
    {
        $padded = strtr($data, '-_', '+/');
        $remainder = strlen($padded) % 4;
        if ($remainder !== 0) {
            $padded .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode($padded, true);
        if ($decoded === false) {
            throw new WebAuthnException('Ungültige base64url-Kodierung.');
        }

        return $decoded;
    }

    /**
     * Baut die PublicKeyCredentialCreationOptions für
     * navigator.credentials.create() (als Array, das der Controller nach
     * JSON kodiert).
     *
     * @param string[] $excludeCredentialIds base64url-kodierte Credential-IDs
     *        bereits registrierter Authenticatoren dieses Benutzers.
     */
    public function buildCreationOptions(
        string $challengeRaw,
        string $rpId,
        string $rpName,
        string $userIdRaw,
        string $userEmail,
        array $excludeCredentialIds,
    ): array {
        return [
            'rp' => ['id' => $rpId, 'name' => $rpName],
            'user' => [
                'id' => self::base64UrlEncode($userIdRaw),
                'name' => $userEmail,
                'displayName' => $userEmail,
            ],
            'challenge' => self::base64UrlEncode($challengeRaw),
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => self::COSE_ALG_ES256],
                ['type' => 'public-key', 'alg' => self::COSE_ALG_RS256],
            ],
            'authenticatorSelection' => [
                'residentKey' => 'preferred',
                'requireResidentKey' => false,
                'userVerification' => 'preferred',
            ],
            'timeout' => self::TIMEOUT_MS,
            'attestation' => 'none',
            'excludeCredentials' => array_map(
                static fn (string $id) => ['type' => 'public-key', 'id' => $id],
                $excludeCredentialIds,
            ),
        ];
    }

    /**
     * Baut die PublicKeyCredentialRequestOptions für
     * navigator.credentials.get(). allowCredentials bleibt bewusst leer:
     * discoverable-Credential-Flow ("usernameless" Login) — der Browser
     * lässt den Benutzer aus allen für diese Seite gespeicherten Passkeys
     * wählen, statt vorher die E-Mail-Adresse abzufragen.
     */
    public function buildRequestOptions(string $challengeRaw, string $rpId): array
    {
        return [
            'challenge' => self::base64UrlEncode($challengeRaw),
            'rpId' => $rpId,
            'timeout' => self::TIMEOUT_MS,
            'userVerification' => 'preferred',
            'allowCredentials' => [],
        ];
    }

    /**
     * Prüft clientDataJSON (Typ, Challenge, Origin) — gemeinsamer Teil von
     * Registrierung und Anmeldung. Wirft WebAuthnException bei jeder
     * Abweichung.
     */
    public function verifyClientData(
        string $clientDataJsonRaw,
        string $expectedType,
        string $expectedChallengeRaw,
        string $expectedOrigin,
    ): array {
        $clientData = json_decode($clientDataJsonRaw, true);
        if (!is_array($clientData)) {
            throw new WebAuthnException('clientDataJSON konnte nicht dekodiert werden.');
        }

        if (($clientData['type'] ?? null) !== $expectedType) {
            throw new WebAuthnException('Unerwarteter clientData-Typ.');
        }

        $challengeFromClient = self::base64UrlDecode((string) ($clientData['challenge'] ?? ''));
        if (!hash_equals($expectedChallengeRaw, $challengeFromClient)) {
            throw new WebAuthnException('Challenge stimmt nicht überein.');
        }

        $origin = (string) ($clientData['origin'] ?? '');
        if (!hash_equals($expectedOrigin, $origin)) {
            throw new WebAuthnException('Origin stimmt nicht überein.');
        }

        return $clientData;
    }

    /**
     * Zerlegt das CBOR-kodierte attestationObject in fmt/attStmt/authData
     * (Rohbytes). Die Attestation-Signatur in attStmt wird bewusst NICHT
     * geprüft (siehe Klassendoku) — nur authData wird weiterverarbeitet.
     *
     * @return array{fmt: string, authData: string}
     */
    public function parseAttestationObject(string $attestationObjectRaw): array
    {
        $decoded = CborDecoder::decodeOne($attestationObjectRaw);
        if (!is_array($decoded) || !isset($decoded['authData'], $decoded['fmt'])) {
            throw new WebAuthnException('attestationObject hat ein unerwartetes Format.');
        }

        return ['fmt' => (string) $decoded['fmt'], 'authData' => (string) $decoded['authData']];
    }

    /**
     * Zerlegt authenticatorData nach WebAuthn Level 2 §6.1:
     * rpIdHash(32) || flags(1) || signCount(4, big-endian) ||
     * [attestedCredentialData] || [extensions].
     *
     * @return array{
     *     rpIdHash: string, userPresent: bool, userVerified: bool,
     *     signCount: int, credentialId: ?string, cosePublicKey: ?array<int|string, mixed>,
     * }
     */
    public function parseAuthenticatorData(string $authData): array
    {
        if (strlen($authData) < 37) {
            throw new WebAuthnException('authenticatorData ist zu kurz.');
        }

        $rpIdHash = substr($authData, 0, 32);
        $flags = ord($authData[32]);
        $signCount = (ord($authData[33]) << 24) | (ord($authData[34]) << 16) | (ord($authData[35]) << 8) | ord($authData[36]);

        $credentialId = null;
        $cosePublicKey = null;

        if (($flags & self::FLAG_ATTESTED_CREDENTIAL_DATA) !== 0) {
            $offset = 37 + 16; // AAGUID (16 Byte) überspringen — für die Anmeldung nicht relevant.
            if (strlen($authData) < $offset + 2) {
                throw new WebAuthnException('authenticatorData: attestedCredentialData ist unvollständig.');
            }

            $credentialIdLength = (ord($authData[$offset]) << 8) | ord($authData[$offset + 1]);
            $offset += 2;

            if (strlen($authData) < $offset + $credentialIdLength) {
                throw new WebAuthnException('authenticatorData: Credential-ID ist unvollständig.');
            }

            $credentialId = substr($authData, $offset, $credentialIdLength);
            $offset += $credentialIdLength;

            $coseKeyBytes = substr($authData, $offset);
            $decoder = new CborDecoder($coseKeyBytes);
            $cosePublicKeyRaw = $decoder->decode();
            if (!is_array($cosePublicKeyRaw)) {
                throw new WebAuthnException('Der öffentliche Schlüssel (COSE) hat ein unerwartetes Format.');
            }
            $cosePublicKey = $cosePublicKeyRaw;
            // Danach könnten noch Extensions folgen (decoder->offset()) — für
            // die hier benötigte Funktionalität (Public Key extrahieren)
            // irrelevant und werden bewusst ignoriert.
        }

        return [
            'rpIdHash' => $rpIdHash,
            'userPresent' => ($flags & self::FLAG_USER_PRESENT) !== 0,
            'userVerified' => ($flags & self::FLAG_USER_VERIFIED) !== 0,
            'signCount' => $signCount,
            'credentialId' => $credentialId,
            'cosePublicKey' => $cosePublicKey,
        ];
    }

    /**
     * Wandelt einen COSE-Public-Key (wie er aus attestedCredentialData
     * extrahiert wurde) in die stabile, JSON-serialisierbare Form um, die
     * in webauthn_credentials.public_key_cose gespeichert wird (alle
     * Binärfelder base64url-kodiert, damit sie sauber als JSON-String-Werte
     * abgelegt werden können).
     *
     * @param array<int|string, mixed> $cosePublicKey
     * @return array<string, mixed>
     */
    public function normalizeCoseKeyForStorage(array $cosePublicKey): array
    {
        $kty = (int) ($cosePublicKey[1] ?? 0);
        $alg = (int) ($cosePublicKey[3] ?? 0);

        if ($kty === self::COSE_KTY_EC2) {
            return [
                'kty' => $kty,
                'alg' => $alg,
                'crv' => (int) ($cosePublicKey[-1] ?? 0),
                'x' => self::base64UrlEncode((string) ($cosePublicKey[-2] ?? '')),
                'y' => self::base64UrlEncode((string) ($cosePublicKey[-3] ?? '')),
            ];
        }

        if ($kty === self::COSE_KTY_RSA) {
            return [
                'kty' => $kty,
                'alg' => $alg,
                'n' => self::base64UrlEncode((string) ($cosePublicKey[-1] ?? '')),
                'e' => self::base64UrlEncode((string) ($cosePublicKey[-2] ?? '')),
            ];
        }

        throw new WebAuthnException('Nicht unterstützter Schlüsseltyp (nur EC2/ES256 und RSA/RS256 werden unterstützt).');
    }

    /**
     * Prüft eine Assertion-Signatur (Anmeldung) gegen den gespeicherten,
     * normalisierten COSE-Key. Die signierten Daten sind laut Spezifikation
     * authenticatorData || SHA-256(clientDataJSON).
     *
     * @param array<string, mixed> $storedCoseKey Ergebnis von normalizeCoseKeyForStorage().
     */
    public function verifyAssertionSignature(
        string $authenticatorDataRaw,
        string $clientDataJsonRaw,
        string $signatureRaw,
        array $storedCoseKey,
    ): bool {
        $signedData = $authenticatorDataRaw . hash('sha256', $clientDataJsonRaw, true);
        $pem = $this->storedCoseKeyToPem($storedCoseKey);
        $alg = (int) $storedCoseKey['alg'];

        if ($alg !== self::COSE_ALG_ES256 && $alg !== self::COSE_ALG_RS256) {
            throw new WebAuthnException('Nicht unterstützter Signaturalgorithmus.');
        }

        $publicKey = openssl_pkey_get_public($pem);
        if ($publicKey === false) {
            throw new WebAuthnException('Der gespeicherte öffentliche Schlüssel konnte nicht geladen werden.');
        }

        // ES256- wie RS256-Signaturen kommen vom Browser bereits DER-kodiert
        // (ASN.1 SEQUENCE aus zwei INTEGERn bei ECDSA bzw. "rohe" RSASSA-PKCS1-v1_5
        // Signatur bei RSA) — exakt das Format, das openssl_verify() erwartet,
        // ohne weitere Umformatierung.
        $result = openssl_verify($signedData, $signatureRaw, $publicKey, OPENSSL_ALGO_SHA256);

        return $result === 1;
    }

    /** @param array<string, mixed> $storedCoseKey */
    private function storedCoseKeyToPem(array $storedCoseKey): string
    {
        $kty = (int) ($storedCoseKey['kty'] ?? 0);

        if ($kty === self::COSE_KTY_EC2) {
            $x = self::base64UrlDecode((string) $storedCoseKey['x']);
            $y = self::base64UrlDecode((string) $storedCoseKey['y']);
            $x = str_pad($x, 32, "\x00", STR_PAD_LEFT);
            $y = str_pad($y, 32, "\x00", STR_PAD_LEFT);

            $der = self::EC_P256_DER_PREFIX . $x . $y;

            return self::derToPem($der);
        }

        if ($kty === self::COSE_KTY_RSA) {
            $n = self::base64UrlDecode((string) $storedCoseKey['n']);
            $e = self::base64UrlDecode((string) $storedCoseKey['e']);

            $der = self::rsaPublicKeyDer($n, $e);

            return self::derToPem($der);
        }

        throw new WebAuthnException('Nicht unterstützter Schlüsseltyp.');
    }

    /**
     * Baut eine RSA-SubjectPublicKeyInfo-DER-Struktur aus Modulus/Exponent,
     * per Hand nach ASN.1/DER (X.509 §4.1) — keine Bibliothek dafür nötig,
     * nur SEQUENCE/INTEGER/BIT STRING/OID-Kodierung.
     */
    private static function rsaPublicKeyDer(string $modulus, string $exponent): string
    {
        $rsaPublicKey = self::derSequence(self::derInteger($modulus) . self::derInteger($exponent));

        // AlgorithmIdentifier { OID rsaEncryption (1.2.840.113549.1.1.1), NULL }
        $rsaEncryptionOid = "\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01";
        $algorithmIdentifier = self::derSequence($rsaEncryptionOid . "\x05\x00");

        return self::derSequence($algorithmIdentifier . self::derBitString($rsaPublicKey));
    }

    private static function derLength(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }

        $bytes = '';
        while ($length > 0) {
            $bytes = chr($length & 0xFF) . $bytes;
            $length >>= 8;
        }

        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    /** Kodiert eine vorzeichenlose Big-Endian-Ganzzahl als DER INTEGER (fügt bei Bedarf ein führendes 0x00 hinzu). */
    private static function derInteger(string $unsignedBigEndian): string
    {
        $bytes = ltrim($unsignedBigEndian, "\x00");
        if ($bytes === '') {
            $bytes = "\x00";
        }
        if ((ord($bytes[0]) & 0x80) !== 0) {
            $bytes = "\x00" . $bytes;
        }

        return "\x02" . self::derLength(strlen($bytes)) . $bytes;
    }

    private static function derSequence(string $content): string
    {
        return "\x30" . self::derLength(strlen($content)) . $content;
    }

    private static function derBitString(string $content): string
    {
        $withUnusedBitsPrefix = "\x00" . $content;

        return "\x03" . self::derLength(strlen($withUnusedBitsPrefix)) . $withUnusedBitsPrefix;
    }

    private static function derToPem(string $der): string
    {
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }
}
