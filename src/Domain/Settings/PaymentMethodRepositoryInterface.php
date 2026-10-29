<?php

declare(strict_types=1);

namespace App\Domain\Settings;

interface PaymentMethodRepositoryInterface
{
    /** @return PaymentMethod[] Standard zuerst, danach alphabetisch */
    public function findAll(): array;

    public function findById(string $id): ?PaymentMethod;

    public function findDefault(): ?PaymentMethod;

    public function save(PaymentMethod $method): void;

    /** Setzt $id als einzige Standard-Zahlungsart. */
    public function markAsDefault(string $id): void;

    public function delete(string $id): void;
}
