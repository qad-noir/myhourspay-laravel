<?php

namespace Tests\Unit;

use App\Services\HoursCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HoursCalculatorTest extends TestCase
{
    private HoursCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new HoursCalculator(2400, 'Europe/London');
    }

    public function test_calculates_integer_minutes_with_default_zero_and_custom_breaks(): void
    {
        $this->assertSame(510, $this->calculator->calculateGrossMinutes('09:00', '17:30'));
        $this->assertSame(480, $this->calculator->calculateNetMinutes('09:00', '17:30', 30));
        $this->assertSame(510, $this->calculator->calculateNetMinutes('09:00', '17:30', 0));
        $this->assertSame(450, $this->calculator->calculateNetMinutes('09:00', '17:30', 60));
    }

    #[DataProvider('invalidShiftProvider')]
    public function test_rejects_invalid_shifts(string $start, string $end, int $break): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->calculateNetMinutes($start, $end, $break);
    }

    public static function invalidShiftProvider(): array
    {
        return [
            'invalid clock' => ['24:00', '25:00', 0],
            'equal clocks' => ['09:00', '09:00', 0],
            'end before start' => ['17:00', '09:00', 0],
            'negative break' => ['09:00', '17:00', -1],
            'break equals shift' => ['09:00', '10:00', 60],
            'break exceeds shift' => ['09:00', '10:00', 61],
        ];
    }

    public function test_formats_long_and_signed_durations(): void
    {
        $this->assertSame('41:30', $this->calculator->formatMinutes(2490));
        $this->assertSame('-00:15', $this->calculator->formatSignedMinutes(-15));
        $this->assertSame('+01:30', $this->calculator->formatSignedMinutes(90));
        $this->assertSame('41h 30m', $this->calculator->formatHumanMinutes(2490));
        $this->assertSame('45m', $this->calculator->formatHumanMinutes(45));
        $this->assertSame('-15m', $this->calculator->formatHumanMinutes(-15));
    }

    public function test_summarizes_iso_weeks_year_boundaries_and_partial_ranges(): void
    {
        $entries = [
            ['id' => 1, 'work_date' => '2026-12-31', 'start_time' => '09:00', 'end_time' => '17:30', 'break_minutes' => 30],
            ['id' => 2, 'work_date' => '2027-01-01', 'start_time' => '09:00', 'end_time' => '17:30', 'break_minutes' => 30],
        ];
        $summary = $this->calculator->summarizeEntries($entries, '2026-12-31', '2027-01-01');

        $this->assertSame('2026-W53', $summary['entries'][0]['week_key']);
        $this->assertSame('16:00', $summary['total_formatted']);
        $this->assertTrue($summary['weeks'][0]['partial']);
        $this->assertSame('-24:00', $summary['weeks'][0]['variance_formatted']);
    }

    public function test_dst_dates_use_local_clock_minutes(): void
    {
        foreach (['2026-03-29', '2026-10-25'] as $date) {
            $entry = $this->calculator->enrichEntry(['id' => 1, 'work_date' => $date, 'start_time' => '00:30', 'end_time' => '08:30', 'break_minutes' => 30]);
            $this->assertSame(450, $entry['net_minutes']);
        }
    }

    public function test_rejects_invalid_calendar_date(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->validateDate('2026-02-30');
    }
}
