<?php

declare(strict_types=1);

namespace App\Domain\Customer;

interface CustomerContactRepositoryInterface
{
    /** @return CustomerContact[] sortiert nach sort_order */
    public function findByCustomerId(string $customerId): array;

    /**
     * Für die Ansprechpartner-Auswahl auf Dokumenten (Rechnung/Angebot/
     * Auftragsbestätigung, siehe Migration 104) — null, wenn kein
     * Ansprechpartner mit dieser ID existiert (z. B. inzwischen gelöscht).
     */
    public function findById(string $id): ?CustomerContact;

    /**
     * Ersetzt ALLE Ansprechpartner eines Kunden durch die übergebene Liste
     * (identisches Muster wie z. B. InvoiceRepositoryInterface::replaceItems()) —
     * einfacher und robuster als ein Diff aus hinzugefügten/entfernten/
     * geänderten Zeilen, bei einer überschaubaren Anzahl Ansprechpartner pro
     * Kunde ohne relevanten Nachteil.
     *
     * @param CustomerContact[] $contacts
     */
    public function replaceForCustomer(string $customerId, array $contacts): void;
}
