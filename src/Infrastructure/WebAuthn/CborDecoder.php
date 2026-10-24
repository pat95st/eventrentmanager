<?php

declare(strict_types=1);

namespace App\Infrastructure\WebAuthn;

use RuntimeException;

/**
 * Handgeschriebener, bewusst minimaler CBOR-Decoder (RFC 8949) — deckt
 * genau die Teilmenge ab, die WebAuthn tatsächlich verwendet: Maps, Byte-
 * und Text-Strings, (un-)signierte Integer und Arrays, dazu die wenigen
 * "simple values" (true/false/null), damit auch unbekannte/ignorierte
 * Strukturen (z. B. eine Attestation-Statement-Map bei fmt != "none")
 * sauber übersprungen werden können, ohne den Decoder zum Absturz zu
 * bringen. Keine allgemeine CBOR-Bibliothek (Composer-Pakete sind in
 * dieser Umgebung nicht installierbar) — bewusst so klein wie möglich
 * gehalten, aber korrekt für die hier vorkommenden Strukturen.
 *
 * Maps werden als PHP-Array mit den Original-Schlüsseln (int ODER string)
 * zurückgegeben; Byte-Strings als rohe PHP-Binärstrings, Text-Strings als
 * UTF-8-PHP-Strings, Integer als PHP-int.
 */
final class CborDecoder
{
    private int $offset = 0;

    public function __construct(private readonly string $data)
    {
    }

    public static function decodeOne(string $data): mixed
    {
        $decoder = new self($data);

        return $decoder->decode();
    }

    /** Position im Byte-Puffer nach dem zuletzt dekodierten Wert (für "wie viele Bytes wurden verbraucht?"). */
    public function offset(): int
    {
        return $this->offset;
    }

    public function decode(): mixed
    {
        $initialByte = $this->readByte();
        $majorType = $initialByte >> 5;
        $additionalInfo = $initialByte & 0x1F;

        return match ($majorType) {
            0 => $this->readUnsignedInt($additionalInfo),
            1 => -1 - $this->readUnsignedInt($additionalInfo),
            2 => $this->readBytes($this->readLength($additionalInfo)),
            3 => $this->readBytes($this->readLength($additionalInfo)),
            4 => $this->decodeArray($this->readLength($additionalInfo)),
            5 => $this->decodeMap($this->readLength($additionalInfo)),
            6 => $this->decodeTagged($additionalInfo),
            7 => $this->decodeSimple($additionalInfo),
            default => throw new RuntimeException('CBOR: unbekannter Major Type.'),
        };
    }

    private function decodeArray(int $length): array
    {
        $result = [];
        for ($i = 0; $i < $length; $i++) {
            $result[] = $this->decode();
        }

        return $result;
    }

    private function decodeMap(int $length): array
    {
        $result = [];
        for ($i = 0; $i < $length; $i++) {
            $key = $this->decode();
            $value = $this->decode();
            $result[$key] = $value;
        }

        return $result;
    }

    /** Tags (Major Type 6) sind für WebAuthn irrelevant — der markierte Wert wird einfach durchgereicht. */
    private function decodeTagged(int $additionalInfo): mixed
    {
        $this->readLength($additionalInfo);

        return $this->decode();
    }

    private function decodeSimple(int $additionalInfo): mixed
    {
        return match ($additionalInfo) {
            20 => false,
            21 => true,
            22, 23 => null,
            24 => $this->readByte(),
            25 => $this->readFloat16(),
            26 => $this->readFloatN(4),
            27 => $this->readFloatN(8),
            default => throw new RuntimeException('CBOR: nicht unterstützter "simple"-Wert.'),
        };
    }

    /** @return int Länge in Bytes bzw. Elementen, oder der Wert selbst bei additionalInfo < 24. */
    private function readLength(int $additionalInfo): int
    {
        return $this->readUnsignedInt($additionalInfo);
    }

    private function readUnsignedInt(int $additionalInfo): int
    {
        if ($additionalInfo < 24) {
            return $additionalInfo;
        }

        return match ($additionalInfo) {
            24 => $this->readByte(),
            25 => $this->readUintN(2),
            26 => $this->readUintN(4),
            27 => $this->readUintN(8),
            default => throw new RuntimeException('CBOR: unbestimmte Länge wird nicht unterstützt (WebAuthn nutzt sie nicht).'),
        };
    }

    private function readByte(): int
    {
        if ($this->offset >= strlen($this->data)) {
            throw new RuntimeException('CBOR: unerwartetes Ende der Daten.');
        }

        return ord($this->data[$this->offset++]);
    }

    private function readBytes(int $length): string
    {
        if ($length < 0 || $this->offset + $length > strlen($this->data)) {
            throw new RuntimeException('CBOR: unerwartetes Ende der Daten.');
        }

        $bytes = substr($this->data, $this->offset, $length);
        $this->offset += $length;

        return $bytes;
    }

    private function readUintN(int $byteCount): int
    {
        $bytes = $this->readBytes($byteCount);
        $value = 0;
        for ($i = 0; $i < $byteCount; $i++) {
            $value = ($value << 8) | ord($bytes[$i]);
        }

        return $value;
    }

    /** Halbpräzision (16 Bit) wird von keinem der hier relevanten Felder genutzt, aber implementiert, um nicht abzustürzen. */
    private function readFloat16(): float
    {
        $bits = $this->readUintN(2);
        $sign = ($bits & 0x8000) ? -1 : 1;
        $exponent = ($bits >> 10) & 0x1F;
        $fraction = $bits & 0x3FF;

        if ($exponent === 0) {
            return $sign * $fraction * 2 ** -24;
        }
        if ($exponent === 0x1F) {
            return $fraction === 0 ? $sign * INF : NAN;
        }

        return $sign * (1 + $fraction / 1024) * 2 ** ($exponent - 15);
    }

    private function readFloatN(int $byteCount): float
    {
        $bytes = strrev($this->readBytes($byteCount)); // CBOR ist Big-Endian, pack()/unpack() erwartet hier Little-Endian.
        $unpacked = unpack($byteCount === 4 ? 'g' : 'e', $bytes);

        return (float) $unpacked[1];
    }
}
