<?php

declare(strict_types=1);

namespace App\Infrastructure\Push;

use App\Domain\Push\PushSubscription;
use RuntimeException;

/**
 * Verschlüsselt (RFC 8291, Content-Encoding "aes128gcm" nach RFC 8188) und
 * versendet (HTTP-POST + VAPID-Autorisierung, RFC 8292) Web-Push-
 * Benachrichtigungen — vollständig von Hand über ext-openssl/ext-curl
 * implementiert, da in dieser Umgebung kein Composer-Paket (insb. kein
 * minishlink/web-push) zuverlässig installierbar ist (siehe SmtpMailer/
 * ImapClient für dasselbe Vorgehen bei anderen Protokollen).
 *
 * encrypt() und send() sind BEWUSST getrennt: encrypt() ist eine reine,
 * seiteneffektfreie Funktion (kein Netzwerkzugriff) und kann daher isoliert
 * getestet werden (siehe bin/push-crypto-selftest.php) — ein Bug in der
 * Verschlüsselung lässt sich so unabhängig vom eigentlichen HTTP-Versand
 * verifizieren.
 */
final class WebPushSender
{
    // Empfohlener RFC-8188-Record-Size-Wert für eine einzelne, kurze
    // Push-Nachricht (wir senden ohnehin immer nur einen einzigen Record).
    private const RECORD_SIZE = 4096;

    private const TIMEOUT_SECONDS = 10;

    public function __construct(
        private readonly string $vapidPrivateKeyPem,
        private readonly string $vapidPublicKeyBase64Url,
        private readonly string $subjectEmail,
    ) {
    }

    /**
     * Verschlüsselt $plaintext für genau EIN Push-Abo nach RFC 8291 und
     * liefert den fertigen HTTP-Body (aes128gcm-Header + Ciphertext) zurück
     * — noch OHNE ihn zu versenden.
     */
    public function encrypt(string $plaintext, string $p256dhBase64Url, string $authBase64Url): string
    {
        $uaPublicRaw = VapidTokenBuilder::base64UrlDecode($p256dhBase64Url);
        $authSecret = VapidTokenBuilder::base64UrlDecode($authBase64Url);

        if (strlen($uaPublicRaw) !== 65 || ord($uaPublicRaw[0]) !== 0x04) {
            throw new RuntimeException('Ungültiger p256dh-Schlüssel (erwartet ein unkomprimierter 65-Byte-EC-P256-Punkt).');
        }

        if (strlen($authSecret) !== 16) {
            throw new RuntimeException('Ungültiger auth-Wert (erwartet 16 Byte).');
        }

        // 1. Frisches, einmaliges EC-P256-Schlüsselpaar NUR für diese eine
        //    Nachricht ("as" = application server, ephemer).
        $asKeyPair = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        if ($asKeyPair === false) {
            throw new RuntimeException('Konnte kein ephemeres EC-Schlüsselpaar erzeugen (ext-openssl prüfen).');
        }

        $asDetails = openssl_pkey_get_details($asKeyPair);
        if ($asDetails === false || !isset($asDetails['ec']['x'], $asDetails['ec']['y'])) {
            throw new RuntimeException('Konnte keine EC-Schlüsseldetails aus dem ephemeren Schlüsselpaar auslesen.');
        }
        $asPublicRaw = "\x04"
            . str_pad((string) $asDetails['ec']['x'], 32, "\x00", STR_PAD_LEFT)
            . str_pad((string) $asDetails['ec']['y'], 32, "\x00", STR_PAD_LEFT);

        $uaPublicKey = openssl_pkey_get_public(self::rawEcPointToPublicKeyPem($uaPublicRaw));
        if ($uaPublicKey === false) {
            throw new RuntimeException('Konnte den p256dh-Schlüssel des Browsers/Geräts nicht laden.');
        }

        // 2. ECDH: ecdh_secret = ECDH(as_private, ua_public) (32 Byte).
        //    openssl_pkey_derive() ist seit PHP 8.1 verfügbar und übernimmt
        //    die eigentliche Punktmultiplikation auf der P-256-Kurve.
        if (!function_exists('openssl_pkey_derive')) {
            throw new RuntimeException('openssl_pkey_derive() ist in dieser PHP-Version nicht verfügbar — ECDH kann nicht durchgeführt werden.');
        }
        $ecdhSecret = openssl_pkey_derive($uaPublicKey, $asKeyPair, 32);
        if ($ecdhSecret === false || strlen($ecdhSecret) !== 32) {
            throw new RuntimeException('ECDH-Schlüsselableitung ist fehlgeschlagen oder lieferte eine unerwartete Länge.');
        }

        // 3. PRK_key = HMAC-SHA256(key=auth_secret, data=ecdh_secret) (HKDF-Extract, auth_secret als Salt).
        $prkKey = Hkdf::extract($authSecret, $ecdhSecret);

        // 4. key_info = "WebPush: info" || 0x00 || ua_public(65) || as_public(65).
        $keyInfo = "WebPush: info\x00" . $uaPublicRaw . $asPublicRaw;

        // 5. IKM = HKDF-Expand(PRK_key, key_info, 32).
        $ikm = Hkdf::expand($prkKey, $keyInfo, 32);

        // 6. Zufälliger 16-Byte-Salt für DIESE Nachricht (RFC 8188).
        $salt = random_bytes(16);

        // 7. PRK = HMAC-SHA256(key=salt, data=IKM) (zweites HKDF-Extract).
        $prk = Hkdf::extract($salt, $ikm);

        // 8./9. CEK (16 Byte) und NONCE (12 Byte) per HKDF-Expand.
        $cek = Hkdf::expand($prk, "Content-Encoding: aes128gcm\x00", 16);
        $nonce = Hkdf::expand($prk, "Content-Encoding: nonce\x00", 12);

        // 10. Klartext-Payload = plaintext || 0x02 (Padding-Trennzeichen für
        //     den letzten/einzigen Record, siehe RFC 8188 §2).
        $paddedPlaintext = $plaintext . chr(0x02);

        // 11. AES-128-GCM, Tag wird separat geliefert und ans Ciphertext angehängt.
        $tag = '';
        $ciphertext = openssl_encrypt($paddedPlaintext, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($ciphertext === false) {
            throw new RuntimeException('AES-128-GCM-Verschlüsselung der Push-Nachricht ist fehlgeschlagen.');
        }
        $ciphertextFinal = $ciphertext . $tag;

        // 12. aes128gcm-Header (RFC 8188 §2.1): salt(16) || recordSize(4, BE)
        //     || keyIdLength(1, = 65) || as_public(65) || ciphertext.
        $header = $salt . pack('N', self::RECORD_SIZE) . chr(strlen($asPublicRaw)) . $asPublicRaw;

        return $header . $ciphertextFinal;
    }

    /** Verschlüsselt $body für $subscription und sendet ihn per HTTP-POST an deren Push-Dienst (Endpoint-URL). */
    public function send(PushSubscription $subscription, string $body): WebPushSendResult
    {
        $encryptedBody = $this->encrypt($body, $subscription->p256dh, $subscription->auth);

        $audience = self::originOf($subscription->endpoint);
        $jwt = VapidTokenBuilder::build($this->vapidPrivateKeyPem, $this->subjectEmail, $audience);
        $authorizationHeader = 'vapid t=' . $jwt . ', k=' . $this->vapidPublicKeyBase64Url;

        if (!function_exists('curl_init')) {
            throw new RuntimeException('ext-curl ist nicht verfügbar — Push-Versand kann nicht durchgeführt werden.');
        }

        $curl = curl_init($subscription->endpoint);
        if ($curl === false) {
            throw new WebPushSendException('curl_init() ist fehlgeschlagen.');
        }

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $encryptedBody,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/octet-stream',
                'Content-Encoding: aes128gcm',
                'TTL: 60',
                'Authorization: ' . $authorizationHeader,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
        ]);

        $responseBody = curl_exec($curl);
        $curlErrno = curl_errno($curl);
        $curlError = curl_error($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($curlErrno !== 0) {
            throw new WebPushSendException("Netzwerkfehler beim Push-Versand (curl #{$curlErrno}): {$curlError}");
        }

        return new WebPushSendResult($statusCode, is_string($responseBody) ? $responseBody : '');
    }

    private static function originOf(string $url): string
    {
        $parts = parse_url($url);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return $scheme . '://' . $host . $port;
    }

    /**
     * Baut aus einem rohen, unkomprimierten EC-P256-Punkt (65 Byte) ein
     * minimales DER-kodiertes SubjectPublicKeyInfo (X.509 "PUBLIC KEY"-PEM),
     * damit openssl_pkey_get_public() ihn laden kann — ext-openssl bietet
     * kein natives "raw EC point importieren"-API.
     *
     * Struktur: SEQUENCE { SEQUENCE { OID id-ecPublicKey, OID prime256v1 },
     * BIT STRING rawPoint }.
     */
    private static function rawEcPointToPublicKeyPem(string $rawPoint): string
    {
        $idEcPublicKeyOid = hex2bin('06072a8648ce3d0201'); // 1.2.840.10045.2.1
        $prime256v1Oid = hex2bin('06082a8648ce3d030107'); // 1.2.840.10045.3.1.7
        $algorithmSequence = self::derSequence($idEcPublicKeyOid . $prime256v1Oid);
        $publicKeyBitString = self::derTlv(0x03, "\x00" . $rawPoint); // 0x00 = keine "unused bits"
        $subjectPublicKeyInfo = self::derSequence($algorithmSequence . $publicKeyBitString);

        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($subjectPublicKeyInfo), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    private static function derSequence(string $content): string
    {
        return self::derTlv(0x30, $content);
    }

    private static function derTlv(int $tag, string $content): string
    {
        return chr($tag) . self::derLength(strlen($content)) . $content;
    }

    private static function derLength(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }

        $bytes = '';
        while ($length > 0) {
            $bytes = chr($length & 0xff) . $bytes;
            $length >>= 8;
        }

        return chr(0x80 | strlen($bytes)) . $bytes;
    }
}
