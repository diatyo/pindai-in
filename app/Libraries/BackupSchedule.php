<?php

namespace App\Libraries;

use DateTimeImmutable;
use DateTimeZone;
use DomainException;

final class BackupSchedule
{
    public static function settings(): array
    {
        return BackupStorage::state('schedule') + ['frequency' => 'off', 'time' => '02:00', 'drive' => false, 'lastSlot' => 0];
    }

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone(config('App')->appTimezone));
    }

    public static function latestSlot(DateTimeImmutable $now, string $frequency, string $time): ?DateTimeImmutable
    {
        if ($frequency === 'off') return null;
        [$hour, $minute] = array_map('intval', explode(':', $time));
        [$start, $previous] = match ($frequency) {
            'daily' => [$now, '-1 day'],
            'weekly' => [$now->modify('monday this week'), '-1 week'],
            'monthly' => [$now->modify('first day of this month'), 'first day of last month'],
            default => throw new DomainException('Jadwal backup tidak dikenal.'),
        };
        $slot = $start->setTime($hour, $minute);
        return $slot <= $now ? $slot : $slot->modify($previous);
    }

    public static function update(string $frequency, string $time, bool $drive): void
    {
        if (! in_array($frequency, ['off', 'daily', 'weekly', 'monthly'], true)
            || ! preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d\z/', $time)) throw new DomainException('Jadwal backup tidak valid.');
        $lock = BackupStorage::lock('job', LOCK_EX);
        try {
            $state = self::settings();
            $new = ['frequency' => $frequency, 'time' => $time, 'drive' => $drive];
            if (array_intersect_key($state, $new) != $new) {
                $state = $new + $state;
                $state['lastSlot'] = self::now()->getTimestamp();
                unset($state['pending'], $state['error']);
                BackupStorage::save('schedule', $state);
            }
        } finally {
            fclose($lock);
        }
    }
}
