<?php

declare(strict_types=1);

namespace App\Infrastructure\Push;

use RuntimeException;

/**
 * Baut und signiert VAPID-JWTs (RFC 8292) sowie ein paar Base64URL-/
 * ASN.1-Hilfsfunktionen, die sowohl hierfür als auch von WebPushSender
 * (Nachrichtenverschlüsselung, RFC 8291) gebraucht werden. Alles von Hand
 * über ext-openssl, kein Composer-Paket verfügbar (siehe SmtpMailer/
 * ImapClient für dasselbe Vorgehen bei anderen Protokollen).
 *
 * ext-openssl liefert ECDSA-Signaturen (openssl_sign mit
 * OPENSSL_ALGO_SHA256) immer DER-kodiert (ASN.1 SEQUENCE aus zwei
 * INTEGERn r und s) — JWT/JOSE (ES256) verlangt dagegen ein festes
 * "r(32 Byte) || s(32 Byte)"-Rohformat. derSignatureToJose() macht genau
 * diese Umwandlung, per Hand geparst (kein ASN.1-Paket verfügbar).
 */
final class VapidTokenBuilder
{
    /**
     * @param string $privateKeyPem PEM-kodierter VAPID-EC-Private-Key (siehe PushSettings::$vapidPrivateKeyPem).
     * @param string $subjectEmail JWT-"sub"-Claim, z. B. "mailto:admin@beispiel.de".
     * @param string $audience Schema+Host(+Port) der Push-Endpoint-URL, z. B. "https://fcm.googleapis.com".
     */
    public static function build(string $privateKeyPem, string $subjectEmail, string $audience, int $ttlSeconds = 12 * 3600): string
    {
        $header = ['typ' => 'JWT', 'alg' => 'ES256'];
        $payload = ['aud' => $audience, 'exp' => time() + $ttlSeconds, 'sub' => $subjectEmail];

        $signingInput = self::base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR))
            . '.' . self::base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));

        $privateKey = openssl_pkey_get_private($privateKeyPem);
        if ($privateKey === false) {
            throw new RuntimeException('VAPID-Private-Key konnte nicht geladen werden (ungültiges PEM?).');
        }

        $derSignature = '';
        if (!openssl_sign($signingInput, $derSignature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('VAPID-JWT konnte nicht signiert werden.');
        }

        return $signingInput . '.' . self::base64UrlEncode(self::derSignatureToJose($derSignature));
    }

    /** Unkomprimierter EC-P256-Public-Key (0x04||X(32)||Y(32)) zum PEM-Private-Key, Base64URL-kodiert. */
    public static function publicKeyBase64UrlFromPrivateKeyPem(string $privateKeyPem): string
    {
        $privateKey = openssl_pkey_get_private($privateKeyPem);
        if ($privateKey === false) {
            throw new RuntimeException('VAPID-Private-Key konnte nicht geladen werden (ungültiges PEM?).');
        }

        $details = openssl_pkey_get_details($privateKey);
        if ($details === false || !isset($details['ec']['x'], $details['ec']['y'])) {
            throw new RuntimeException('Konnte keine EC-Schlüsseldetails aus dem VAPID-Private-Key auslesen.');
        }

        $x = str_pad((string) $details['ec']['x'], 32, "\x00", STR_PAD_LEFT);
        $y = str_pad((string) $details['ec']['y'], 32, "\x00", STR_PAD_LEFT);

        return self::base64UrlEncode("\x04" . $x . $y);
    }

    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $data): string
    {
        $padded = strtr($data, '-_', '+/');
        $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);

        $decoded = base64_decode($padded, true);
        if ($decoded === false) {
            throw new RuntimeException('Ungültige Base64URL-Kodierung.');
        }

        return $decoded;
    }

    /** DER SEQUENCE(INTEGER r, INTEGER s) -> rohes "r(32) || s(32)"-JOSE-Format. */
    private static function derSignatureToJose(string $der): string
    {
        $offset = 0;
        self::expectDerTag($der, $offset, 0x30); // SEQUENCE
        self::readDerLength($der, $offset); // Gesamtlänge, wird nicht weiter gebraucht.

        self::expectDerTag($der, $offset, 0x02); // INTEGER r
        $rLength = self::readDerLength($der, $offset);
        $r = substr($der, $offset, $rLength);
        $offset += $rLength;

        self::expectDerTag($der, $offset, 0x02); // INTEGER s
        $sLength = self::readDerLength($der, $offset);
        $s = substr($der, $offset, $sLength);
        $offset += $sLength;

        return self::fixedLengthUnsigned($r, 32) . self::fixedLengthUnsigned($s, 32);
    }

    /** Entfernt ein führendes 0x00-Vorzeichen-Byte (DER-INTEGER sind vorzeichenbehaftet) und füllt auf $length Byte auf. */
    private static function fixedLengthUnsigned(string $bytes, int $length): string
    {
        while (strlen($bytes) > $length && ord($bytes[0]) === 0x00) {
            $bytes = substr($bytes, 1);
        }

        if (strlen($bytes) > $length) {
            throw new RuntimeException('Unerwartete Länge einer ECDSA-Signaturkomponente.');
        }

        return str_pad($bytes, $length, "\x00", STR_PAD_LEFT);
    }

    private static function expectDerTag(string $der, int &$offset, int $expectedTag): void
    {
        if ($offset >= strlen($der) || ord($der[$offset]) !== $expectedTag) {
            throw new RuntimeException('Unerwartetes ASN.1/DER-Format in der ECDSA-Signatur.');
        }
        $offset++;
    }

    private static function readDerLength(string $der, int &$offset): int
    {
        if ($offset >= strlen($der)) {
            throw new RuntimeException('Unerwartetes Ende der ASN.1/DER-Struktur.');
        }

        $first = ord($der[$offset]);
        $offset++;

        if (($first & 0x80) === 0) {
            return $first;
        }

        $byteCount = $first & 0x7f;
        $length = 0;
        for ($i = 0; $i < $byteCount; $i++) {
            $length = ($length << 8) | ord($der[$offset]);
            $offset++;
        }

        return $length;
    }
}
