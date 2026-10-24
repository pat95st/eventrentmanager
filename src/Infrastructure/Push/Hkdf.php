<?php

declare(strict_types=1);

namespace App\Infrastructure\Push;

use InvalidArgumentException;

/**
 * Minimale HKDF-Implementierung (RFC 5869) über HMAC-SHA256, ausschließlich
 * für den in RFC 8291 beschriebenen Web-Push-Verschlüsselungsablauf (siehe
 * WebPushSender::encrypt()). Bewusst kein Composer-Paket (siehe README/
 * Projektdoku zu SmtpMailer/ImapClient) — alles über ext-hash/ext-openssl.
 *
 * expand() ist bewusst auf höchstens einen Hash-Block (32 Byte, das
 * SHA-256-Digest) beschränkt: alle in RFC 8291 benötigten Längen (IKM = 32,
 * CEK = 16, NONCE = 12 Byte) passen genau in T(1) = HMAC-SHA256(PRK, info ||
 * 0x01) — ein vollständiger HKDF-Expand mit mehreren Blöcken (T(2), T(3), …)
 * ist für diesen Anwendungsfall nicht nötig und daher nicht implementiert.
 */
final class Hkdf
{
    /** HKDF-Extract: PRK = HMAC-SHA256(salt, ikm). */
    public static function extract(string $salt, string $ikm): string
    {
        return hash_hmac('sha256', $ikm, $salt, true);
    }

    /** HKDF-Expand, beschränkt auf length <= 32 (siehe Klassendoku). */
    public static function expand(string $prk, string $info, int $length): string
    {
        if ($length < 1 || $length > 32) {
            throw new InvalidArgumentException('Hkdf::expand unterstützt in dieser minimalen Implementierung nur 1..32 Byte (ein Hash-Block).');
        }

        $t1 = hash_hmac('sha256', $info . chr(0x01), $prk, true);

        return substr($t1, 0, $length);
    }
}
