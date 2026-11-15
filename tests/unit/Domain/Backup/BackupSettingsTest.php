<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Backup;

use App\Domain\Backup\BackupInterval;
use App\Domain\Backup\BackupSettings;
use Codeception\Test\Unit;

final class BackupSettingsTest extends Unit
{
    public function testDailyCronSchedule(): void
    {
        $settings = new BackupSettings(interval: BackupInterval::Daily, hour: 3, minute: 5);

        $this->assertSame('5 3 * * *', $settings->cronSchedule());
    }

    public function testWeeklyCronScheduleMapsSundayToZero(): void
    {
        $this->assertSame('30 22 * * 1', (new BackupSettings(interval: BackupInterval::Weekly, hour: 22, minute: 30, weekday: 1))->cronSchedule());
        $this->assertSame('0 0 * * 0', (new BackupSettings(interval: BackupInterval::Weekly, hour: 0, minute: 0, weekday: 7))->cronSchedule());
    }

    public function testMonthlyCronSchedule(): void
    {
        $settings = new BackupSettings(interval: BackupInterval::Monthly, hour: 4, minute: 45, monthDay: 28);

        $this->assertSame('45 4 28 * *', $settings->cronSchedule());
    }
}
