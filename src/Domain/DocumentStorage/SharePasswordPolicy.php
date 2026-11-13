<?php

declare(strict_types=1);

namespace App\Domain\DocumentStorage;

/**
 * Richtlinie, nach der das Passwort einer Ordner-Freigabe automatisch erzeugt
 * wird (je Ziellaufwerk einstellbar, Migration 148). Das Passwort wird nie
 * gespeichert, sondern nur einmalig nach dem Erzeugen angezeigt.
 *
 * Leicht verwechselbare Zeichen (0/O, 1/l/I) fehlen bewusst: das Passwort wird
 * dem Kunden häufig am Telefon durchgegeben oder abgetippt.
 */
final class SharePasswordPolicy
{
    public const DEFAULT_LENGTH = 16;
    public const MIN_LENGTH = 8;
    public const MAX_LENGTH = 64;

    private const UPPERCASE = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    private const LOWERCASE = 'abcdefghijkmnopqrstuvwxyz';
    private const DIGITS = '23456789';
    private const SYMBOLS = '!#$%&*+-=?@_';

    public function __construct(
        public readonly int $length = self::DEFAULT_LENGTH,
        public readonly bool $uppercase = true,
        public readonly bool $lowercase = true,
        public readonly bool $digits = true,
        public readonly bool $symbols = false,
    ) {
    }

    /** @return array<string, string> Feldname => Fehlermeldung, leer = gültig. */
    public function validate(): array
    {
        $errors = [];
        if ($this->length < self::MIN_LENGTH || $this->length > self::MAX_LENGTH) {
            $errors['sharePasswordLength'] = 'Bitte eine Passwortlänge zwischen ' . self::MIN_LENGTH . ' und ' . self::MAX_LENGTH . ' Zeichen angeben.';
        }
        if ($this->alphabets() === []) {
            $errors['sharePasswordClasses'] = 'Bitte mindestens eine Zeichenart für das Passwort auswählen.';
        }

        return $errors;
    }

    /** Erzeugt ein Passwort, das aus jeder gewählten Zeichenart mindestens ein Zeichen enthält. */
    public function generate(): string
    {
        $alphabets = $this->alphabets();
        if ($alphabets === []) {
            $alphabets = [self::UPPERCASE, self::LOWERCASE, self::DIGITS];
        }
        $length = max(count($alphabets), min(self::MAX_LENGTH, max(self::MIN_LENGTH, $this->length)));

        $characters = [];
        foreach ($alphabets as $alphabet) {
            $characters[] = $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $all = implode('', $alphabets);
        while (count($characters) < $length) {
            $characters[] = $all[random_int(0, strlen($all) - 1)];
        }

        // Fisher-Yates mit random_int(): die Pflichtzeichen sollen nicht immer vorne stehen.
        for ($i = count($characters) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$characters[$i], $characters[$j]] = [$characters[$j], $characters[$i]];
        }

        return implode('', $characters);
    }

    /** @return list<string> */
    private function alphabets(): array
    {
        return array_values(array_filter([
            $this->uppercase ? self::UPPERCASE : null,
            $this->lowercase ? self::LOWERCASE : null,
            $this->digits ? self::DIGITS : null,
            $this->symbols ? self::SYMBOLS : null,
        ]));
    }
}
