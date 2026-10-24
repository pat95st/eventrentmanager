<?php

declare(strict_types=1);

namespace App\Domain\Api;

use DateTimeImmutable;

/**
 * Ein von einem Administrator ausgestelltes API-Token für die optionale
 * Lesezugriff-REST-API (siehe ArticleApiController). Es wird — wie bei
 * PasswordSetupToken — nur der Hash des Klartext-Tokens gespeichert
 * ($tokenHash); der Klartext wird ausschließlich einmalig direkt nach dem
 * Anlegen angezeigt und danach nie wieder rekonstruierbar. $tokenLastFour
 * hält die letzten vier Zeichen des Klartexts, damit ein Token in der Liste
 * wiedererkannt werden kann, ohne dass der volle Wert erneut sichtbar sein
 * muss (analog zu "Kreditkarte endet auf …").
 */
final class ApiToken
{
    public function __construct(
        public readonly string $id,
        public string $name,
        public readonly string $tokenHash,
        public readonly string $tokenLastFour,
        // Erlaubte Client-IPs/-Netze, eine pro Zeile (einzelne IPv4-/IPv6-Adresse
        // oder CIDR-Notation wie "203.0.113.0/24"). NULL/leer = von überall
        // erlaubt (siehe IpAllowlist::matches()).
        public ?string $ipAllowlist,
        public bool $enabled,
        public ?DateTimeImmutable $lastUsedAt,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }
}
