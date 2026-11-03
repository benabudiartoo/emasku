<?php

namespace Tests\Unit;

use App\Services\Zakat\HijriCalendar;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class HijriCalendarTest extends TestCase
{
    private HijriCalendar $hijri;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hijri = new HijriCalendar;
    }

    public function test_known_umm_al_qura_dates(): void
    {
        $this->assertSame('1447-01-01', $this->hijri->format('2025-06-26')); // 1 Muharram 1447
        $this->assertSame('1447-10-01', $this->hijri->format('2026-03-20')); // 1 Syawal 1447
        $this->assertSame(['year' => 1447, 'month' => 1, 'day' => 1], $this->hijri->toHijri('2025-06-26'));
    }

    public function test_add_one_hijri_year_keeps_the_hijri_day_and_month(): void
    {
        foreach (['2025-06-26', '2026-03-20', '2026-11-03', '2024-02-29'] as $start) {
            $end = $this->hijri->addHijriYear($start);
            $a = $this->hijri->toHijri($start);
            $b = $this->hijri->toHijri($end);

            $this->assertSame($a['year'] + 1, $b['year'], $start);
            $this->assertSame($a['month'], $b['month'], $start);
            $this->assertSame($a['day'], $b['day'], $start);
        }
    }

    public function test_a_hijri_year_is_about_354_days_not_365(): void
    {
        $days = (strtotime($this->hijri->addHijriYear('2025-06-26')) - strtotime('2025-06-26')) / 86400;

        $this->assertGreaterThanOrEqual(353, $days);
        $this->assertLessThanOrEqual(355, $days);
    }

    public function test_year_boundary_rolls_over(): void
    {
        // Last day of 1447 plus one Hijri year lands in 1448, still the same Hijri day.
        $end = $this->hijri->addHijriYear('2025-06-26');
        $this->assertSame('1448-01-01', $this->hijri->format($end));
    }

    public function test_malformed_dates_are_rejected(): void
    {
        foreach (['2026-13-01', '2026-02-30', 'bukan tanggal', '03/11/2026', ''] as $bad) {
            try {
                $this->hijri->format($bad);
                $this->fail("Expected rejection of '{$bad}'");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_method_and_tolerance_are_documented_constants(): void
    {
        $this->assertSame('umm-al-qura', HijriCalendar::METHOD);
        $this->assertSame(1, HijriCalendar::TOLERANCE_DAYS);
    }
}
