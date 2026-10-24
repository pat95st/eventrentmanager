<?php

declare(strict_types=1);

namespace App\Domain\Calendar;

interface CalendarRepositoryInterface
{
    public function save(Calendar $calendar): void;

    public function findById(string $id): ?Calendar;

    /** @return Calendar[] sortiert: der Event-Kalender zuerst, danach die Vermietungskalender nach Name */
    public function findAll(): array;

    public function delete(string $id): void;
}
