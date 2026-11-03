<?php

namespace App\Services\Zakat;

use DateTimeImmutable;
use DateTimeZone;
use IntlCalendar;
use InvalidArgumentException;

/**
 * Gregorian <-> Hijri conversion with the Umm al-Qura calendar (ICU `islamic-umalqura`).
 *
 * Umm al-Qura is a calculated calendar. Other methods (moon sighting, other tables) can differ by one day,
 * so a haul boundary computed here is accurate to within ±1 day. Dates are plain 'Y-m-d' strings, handled at
 * midday UTC so no time zone or daylight-saving shift can move the day.
 */
class HijriCalendar
{
    public const METHOD = 'umm-al-qura';

    public const TOLERANCE_DAYS = 1;

    /** @return array{year: int, month: int, day: int} */
    public function toHijri(string $gregorian): array
    {
        $cal = $this->calendarAt($gregorian);

        return [
            'year' => $cal->get(IntlCalendar::FIELD_YEAR),
            'month' => $cal->get(IntlCalendar::FIELD_MONTH) + 1, // ICU months are 0-based
            'day' => $cal->get(IntlCalendar::FIELD_DAY_OF_MONTH),
        ];
    }

    /** '1447-04-09' (Hijri year-month-day, zero padded). */
    public function format(string $gregorian): string
    {
        ['year' => $y, 'month' => $m, 'day' => $d] = $this->toHijri($gregorian);

        return sprintf('%04d-%02d-%02d', $y, $m, $d);
    }

    /**
     * The Gregorian date one Hijri year after $gregorian: the same Hijri day and month, year + 1.
     * If that day does not exist in the target month (day 30 of a 29-day month) it falls on the month's last day.
     */
    public function addHijriYear(string $gregorian): string
    {
        $cal = $this->calendarAt($gregorian);
        $cal->add(IntlCalendar::FIELD_YEAR, 1);

        return $this->toGregorian($cal);
    }

    private function calendarAt(string $gregorian): IntlCalendar
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $gregorian, new DateTimeZone('UTC'));
        if ($date === false || $date->format('Y-m-d') !== $gregorian) {
            throw new InvalidArgumentException("Not a Y-m-d date: {$gregorian}");
        }

        $cal = IntlCalendar::createInstance('UTC', 'en@calendar=islamic-umalqura');
        $cal->setTime(($date->getTimestamp() + 12 * 3600) * 1000);

        return $cal;
    }

    private function toGregorian(IntlCalendar $cal): string
    {
        return gmdate('Y-m-d', (int) floor($cal->getTime() / 1000));
    }
}
