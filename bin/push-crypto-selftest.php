#!/usr/bin/env php
<?php

declare(strict_types=1);

// Eigenständiger kryptografischer Selbsttest für den Web-Push-Versand
// (siehe src/Infrastructure/Push/WebPushSender.php und VapidTokenBuilder.php).
// Läuft OHNE Datenbankverbindung und OHNE echten HTTP-Versand — reine
// Verschlüsselungs-/Signatur-Selbstprüfung, siehe die zwei Teile unten:
//
//   1. RFC-8291-Nachrichtenverschlüsselung ("Hallo Push!"): WebPushSender
//      verschlüsselt die Nachricht für ein simuliertes Browser-Schlüsselpaar,
//      dieses Skript entschlüsselt sie DANACH UNABHÄNGIG (eigener, frisch
//      geschriebener Code — nicht die Hkdf-/WebPushSender-Klassen der
//      Anwendung), um einen versteckten Bug nicht durch denselben Bug auf
//      beiden Seiten zu übersehen.
//   2. VAPID-JWT (RFC 8292): VapidTokenBuilder signiert ein JWT, dieses
//      Skript verifiziert die ECDSA-Signatur unabhängig per openssl_verify().
//
// Aufruf: php bin/push-crypto-selftest.php

require_once __DIR__ . '/../src/autoload.php';

use App\Infrastructure\Push\VapidTokenBuilder;
use App\Infrastructure\Push\WebPushSender;

function b64u(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function b64u_decode(string $data): string
{
    $padded = strtr($data, '-_', '+/');
    $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);
    $decoded = base64_decode($padded, true);
    if ($decoded === false) {
        throw new RuntimeException('Ungültige Base64URL-Kodierung.');
    }

    return $decoded;
}

function derLength(int $length): string
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

function derSeq(string $content): string
{
    return "\x30" . derLength(strlen($content)) . $content;
}

function derTlv(int $tag, string $content): string
{
    return chr($tag) . derLength(strlen($content)) . $content;
}

/** Baut aus einem rohen unkomprimierten EC-P256-Punkt ein ladbares PEM-Public-Key (unabhängige Implementierung, s.o.). */
function rawEcPointToPem(string $rawPoint): string
{
    $idEcPublicKeyOid = hex2bin('06072a8648ce3d0201');
    $prime256v1Oid = hex2bin('06082a8648ce3d030107');
    $algorithmSeq = derSeq($idEcPublicKeyOid . $prime256v1Oid);
    $bitString = derTlv(0x03, "\x00" . $rawPoint);
    $spki = derSeq($algorithmSeq . $bitString);

    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

$failures = 0;

echo "=== 1) RFC-8291-Nachrichtenverschlüsselung (\"Hallo Push!\") ===\n";

try {
    // a) Simuliertes Browser/Client-Schlüsselpaar + auth-Wert (das, was normalerweise pushManager.subscribe() liefert).
    $clientKeyPair = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    if ($clientKeyPair === false) {
        throw new RuntimeException('Konnte kein simuliertes Client-Schlüsselpaar erzeugen.');
    }
    $clientDetails = openssl_pkey_get_details($clientKeyPair);
    $clientPublicRaw = "\x04"
        . str_pad($clientDetails['ec']['x'], 32, "\x00", STR_PAD_LEFT)
        . str_pad($clientDetails['ec']['y'], 32, "\x00", STR_PAD_LEFT);
    $p256dh = b64u($clientPublicRaw);
    $authSecret = random_bytes(16);
    $auth = b64u($authSecret);

    echo "Simuliertes p256dh (Client-Public-Key, 65 Byte roh, b64u): " . substr($p256dh, 0, 24) . "...\n";
    echo "Simulierter auth-Wert (16 Byte, b64u): {$auth}\n";

    // b) WebPushSender verschlüsselt "Hallo Push!" für dieses simulierte Abo (NUR encrypt(), kein HTTP-Versand).
    $sender = new WebPushSender('dummy-unused-in-encrypt', 'dummy-unused-in-encrypt', 'mailto:test@example.com');
    $plaintext = 'Hallo Push!';
    $encrypted = $sender->encrypt($plaintext, $p256dh, $auth);

    echo "WebPushSender::encrypt() lieferte " . strlen($encrypted) . " Byte (Header + Ciphertext).\n";

    // c) UNABHÄNGIGE Entschlüsselung (eigener Code in diesem Skript, nicht Hkdf/WebPushSender der Anwendung).
    $salt = substr($encrypted, 0, 16);
    $recordSize = unpack('N', substr($encrypted, 16, 4))[1];
    $keyIdLength = ord($encrypted[20]);
    $asPublicRaw = substr($encrypted, 21, $keyIdLength);
    $ciphertextWithTag = substr($encrypted, 21 + $keyIdLength);

    if ($keyIdLength !== 65 || strlen($asPublicRaw) !== 65 || ord($asPublicRaw[0]) !== 0x04) {
        throw new RuntimeException('aes128gcm-Header enthält keinen gültigen 65-Byte-EC-P256-Punkt als keyId.');
    }

    $asPublicKey = openssl_pkey_get_public(rawEcPointToPem($asPublicRaw));
    if ($asPublicKey === false) {
        throw new RuntimeException('Konnte den im Header enthaltenen as_public-Schlüssel nicht laden.');
    }

    $ecdhSecret = openssl_pkey_derive($asPublicKey, $clientKeyPair, 32);
    if ($ecdhSecret === false || strlen($ecdhSecret) !== 32) {
        throw new RuntimeException('Client-seitiges ECDH (openssl_pkey_derive) ist fehlgeschlagen.');
    }

    $hmac = static fn (string $key, string $data): string => hash_hmac('sha256', $data, $key, true);

    $prkKey = $hmac($authSecret, $ecdhSecret); // HKDF-Extract, auth als Salt
    $keyInfo = "WebPush: info\x00" . $clientPublicRaw . $asPublicRaw;
    $ikm = substr($hmac($prkKey, $keyInfo . chr(0x01)), 0, 32); // HKDF-Expand, ein Block reicht (32 Byte)

    $prk = $hmac($salt, $ikm); // zweites HKDF-Extract, Nachrichten-Salt
    $cek = substr($hmac($prk, "Content-Encoding: aes128gcm\x00" . chr(0x01)), 0, 16);
    $nonce = substr($hmac($prk, "Content-Encoding: nonce\x00" . chr(0x01)), 0, 12);

    $ciphertext = substr($ciphertextWithTag, 0, -16);
    $tag = substr($ciphertextWithTag, -16);

    $decryptedPadded = openssl_decrypt($ciphertext, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);

    if ($decryptedPadded === false) {
        throw new RuntimeException('AES-128-GCM-Entschlüsselung ist fehlgeschlagen (Auth-Tag-Prüfung nicht bestanden).');
    }

    $expected = $plaintext . chr(0x02);

    echo 'Entschlüsselt (hex): ' . bin2hex($decryptedPadded) . "\n";
    echo 'Erwartet    (hex): ' . bin2hex($expected) . "\n";

    if ($decryptedPadded === $expected) {
        echo "ERGEBNIS: ERFOLG — entschlüsselter Klartext entspricht exakt \"Hallo Push!\" . chr(0x02).\n";
    } else {
        echo "ERGEBNIS: FEHLER — entschlüsselter Klartext weicht ab.\n";
        $failures++;
    }
} catch (Throwable $e) {
    echo 'ERGEBNIS: FEHLER — Ausnahme: ' . $e->getMessage() . "\n";
    $failures++;
}

echo "\n=== 2) VAPID-JWT-Selbsttest (RFC 8292) ===\n";

try {
    $vapidKeyPair = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    if ($vapidKeyPair === false) {
        throw new RuntimeException('Konnte kein VAPID-Test-Schlüsselpaar erzeugen.');
    }
    $vapidPrivatePem = '';
    if (!openssl_pkey_export($vapidKeyPair, $vapidPrivatePem)) {
        throw new RuntimeException('Konnte den VAPID-Test-Private-Key nicht exportieren.');
    }
    $vapidPublicB64 = VapidTokenBuilder::publicKeyBase64UrlFromPrivateKeyPem($vapidPrivatePem);

    $jwt = VapidTokenBuilder::build($vapidPrivatePem, 'mailto:admin@example.com', 'https://fcm.googleapis.com');
    echo 'JWT: ' . substr($jwt, 0, 48) . "...\n";

    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        throw new RuntimeException('JWT hat nicht drei Teile.');
    }
    [$headerB64, $payloadB64, $sigB64] = $parts;
    $signingInput = $headerB64 . '.' . $payloadB64;
    $joseSignature = b64u_decode($sigB64);

    if (strlen($joseSignature) !== 64) {
        throw new RuntimeException('JOSE-Signatur hat nicht die erwarteten 64 Byte (r(32)||s(32)).');
    }

    $r = substr($joseSignature, 0, 32);
    $s = substr($joseSignature, 32, 32);

    // Unabhängige Rückumwandlung JOSE (r||s) -> DER, um openssl_verify() nutzen zu können.
    $derInt = static function (string $bytes): string {
        $bytes = ltrim($bytes, "\x00");
        if ($bytes === '') {
            $bytes = "\x00";
        }
        if ((ord($bytes[0]) & 0x80) !== 0) {
            $bytes = "\x00" . $bytes;
        }

        return "\x02" . derLength(strlen($bytes)) . $bytes;
    };

    $derSignature = derSeq($derInt($r) . $derInt($s));

    // SubjectPublicKeyInfo für P-256 von Hand zusammensetzen (fester Präfix + roher Punkt), unabhängig geprüft.
    $spkiPrefix = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200');
    $publicKeyPem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spkiPrefix . b64u_decode($vapidPublicB64)), 64, "\n") . "-----END PUBLIC KEY-----\n";
    $publicKeyResource = openssl_pkey_get_public($publicKeyPem);
    if ($publicKeyResource === false) {
        throw new RuntimeException('Konnte den VAPID-Public-Key für die Verifikation nicht laden.');
    }

    $verifyResult = openssl_verify($signingInput, $derSignature, $publicKeyResource, OPENSSL_ALGO_SHA256);

    echo 'openssl_verify()-Ergebnis: ' . var_export($verifyResult, true) . "\n";

    if ($verifyResult === 1) {
        echo "ERGEBNIS: ERFOLG — VAPID-JWT-Signatur wurde unabhängig per openssl_verify() als gültig (== 1) bestätigt.\n";
    } else {
        echo "ERGEBNIS: FEHLER — VAPID-JWT-Signatur konnte nicht verifiziert werden.\n";
        $failures++;
    }
} catch (Throwable $e) {
    echo 'ERGEBNIS: FEHLER — Ausnahme: ' . $e->getMessage() . "\n";
    $failures++;
}

echo "\n" . ($failures === 0 ? 'ALLE SELBSTTESTS ERFOLGREICH.' : "{$failures} SELBSTTEST(S) FEHLGESCHLAGEN.") . "\n";

exit($failures === 0 ? 0 : 1);
