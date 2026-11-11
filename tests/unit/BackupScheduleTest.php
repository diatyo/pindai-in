<?php

namespace Tests\Unit;

use App\Libraries\BackupSchedule;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class BackupScheduleTest extends TestCase
{
    public function testDailySlotRemainsDueAfterDowntimeAcrossMidnight(): void
    {
        $now = new DateTimeImmutable('2026-11-12 00:10', new DateTimeZone('Asia/Jakarta'));
        self::assertSame('2026-11-11 02:00', BackupSchedule::latestSlot($now, 'daily', '02:00')->format('Y-m-d H:i'));
        self::assertSame('2026-11-12 02:00', BackupSchedule::latestSlot($now->modify('+2 hours'), 'daily', '02:00')->format('Y-m-d H:i'));
    }

    public function testWeeklyAndMonthlySlotsUseTheMostRecentElapsedDate(): void
    {
        $zone = new DateTimeZone('Asia/Jakarta');
        $monday = new DateTimeImmutable('2026-11-09 01:00', $zone);
        $monthStart = new DateTimeImmutable('2027-01-01 00:30', $zone);
        self::assertSame('2026-11-02 02:00', BackupSchedule::latestSlot($monday, 'weekly', '02:00')->format('Y-m-d H:i'));
        self::assertSame('2026-12-01 02:00', BackupSchedule::latestSlot($monthStart, 'monthly', '02:00')->format('Y-m-d H:i'));
        self::assertNull(BackupSchedule::latestSlot($monthStart, 'off', '02:00'));
    }
}
