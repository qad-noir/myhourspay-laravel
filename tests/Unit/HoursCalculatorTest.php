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

    public function test_paid_breaks_are_included_and_break_totals_are_separated(): void
    {
        $this->assertSame(510, $this->calculator->calculateNetMinutes('09:00', '17:30', 30, 'paid'));
        $summary = $this->calculator->summarizeEntries([
            ['work_date' => '2026-08-03', 'start_time' => '09:00', 'end_time' => '17:30', 'break_minutes' => 30, 'break_type' => 'paid'],
            ['work_date' => '2026-08-04', 'start_time' => '09:00', 'end_time' => '17:30', 'break_minutes' => 45, 'break_type' => 'unpaid'],
            ['work_date' => '2026-08-05', 'start_time' => '09:00', 'end_time' => '17:00', 'break_minutes' => 0, 'break_type' => 'paid'],
        ]);

        $this->assertSame(2, $summary['break_count']);
        $this->assertSame(30, $summary['paid_break_minutes']);
        $this->assertSame(45, $summary['unpaid_break_minutes']);
    }

    public function test_overtime_sums_positive_weekly_excess_without_negative_offsets(): void
    {
        $summary = $this->calculator->summarizeEntries([
            ['work_date' => '2026-08-03', 'start_time' => '00:00', 'end_time' => '23:00', 'break_minutes' => 0, 'break_type' => 'paid'],
            ['work_date' => '2026-08-04', 'start_time' => '00:00', 'end_time' => '23:00', 'break_minutes' => 0, 'break_type' => 'paid'],
            ['work_date' => '2026-08-10', 'start_time' => '09:00', 'end_time' => '17:00', 'break_minutes' => 0, 'break_type' => 'unpaid'],
        ]);

        $this->assertSame(360, $summary['overtime_minutes']);
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

    public function test_daily_excess_is_not_cancelled_by_shorter_days_and_both_totals_are_available(): void
    {
        $entries = [];
        foreach (['2026-10-05' => '19:00', '2026-10-06' => '16:00', '2026-10-07' => '16:00', '2026-10-08' => '16:00', '2026-10-09' => '16:00'] as $date => $end) {
            $entries[] = ['work_date' => $date, 'start_time' => '09:00', 'end_time' => $end, 'break_minutes' => 0];
        }
        $daily = new HoursCalculator(2400, 'Europe/London', 480, 'daily');
        $summary = $daily->summarizeEntries($entries, '2026-10-05', '2026-10-11', false);
        $this->assertSame(2280, $summary['total_minutes']);
        $this->assertSame(120, $summary['overtime_minutes']);
        $this->assertSame(120, $summary['daily_overtime_minutes']);
        $this->assertSame(0, $summary['weekly_overtime_minutes']);
        $this->assertSame(-120, $summary['weeks'][0]['variance_minutes']);
        $this->assertSame(120, $summary['weeks'][0]['overtime_minutes']);
        $this->assertSame([], $summary['entries']);
        $weekly = (new HoursCalculator(2400, 'Europe/London', 480, 'weekly'))->summarizeEntries($entries);
        $this->assertSame(0, $weekly['overtime_minutes']);
        $this->assertSame(120, $weekly['daily_overtime_minutes']);
    }

    public function test_daily_threshold_uses_net_minutes_and_aggregates_a_date_once(): void
    {
        $calculator = new HoursCalculator(2400, 'Europe/London', 450, 'daily');
        $summary = $calculator->summarizeEntries([
            ['work_date' => '2026-10-05', 'start_time' => '09:00', 'end_time' => '17:00', 'break_minutes' => 30, 'break_type' => 'unpaid'],
            ['work_date' => '2026-10-06', 'start_time' => '09:00', 'end_time' => '17:00', 'break_minutes' => 30, 'break_type' => 'paid'],
            ['work_date' => '2026-10-07', 'start_time' => '09:00', 'end_time' => '13:00', 'break_minutes' => 0],
            ['work_date' => '2026-10-07', 'start_time' => '14:00', 'end_time' => '19:00', 'break_minutes' => 0],
        ]);
        $this->assertSame(120, $summary['daily_overtime_minutes']);
        $this->assertSame(0, $summary['entries'][0]['daily_overtime_minutes']);
        $this->assertSame(30, $summary['entries'][1]['daily_overtime_minutes']);
        $this->assertSame(90, $summary['entries'][2]['daily_overtime_minutes']);
        $this->assertSame(1470, $summary['total_minutes']);
        $this->assertSame(30, $summary['paid_break_minutes']);
        $this->assertSame(30, $summary['unpaid_break_minutes']);
    }

    public function test_daily_period_does_not_include_outside_dates_when_using_full_week_comparison(): void
    {
        $calculator = new HoursCalculator(2400, 'Europe/London', 480, 'daily');
        $inside = ['work_date' => '2026-10-01', 'start_time' => '09:00', 'end_time' => '18:00', 'break_minutes' => 0];
        $outside = ['work_date' => '2026-09-30', 'start_time' => '00:00', 'end_time' => '23:00', 'break_minutes' => 0];
        $period = $calculator->summarizeEntries([$inside], '2026-10-01', '2026-10-31');
        $full = $calculator->summarizeEntries([$outside, $inside], '2026-09-28', '2026-11-01');
        $combined = $calculator->withFullWeekOvertime($period, $full);
        $this->assertSame(60, $combined['overtime_minutes']);
        $this->assertSame(60, $combined['daily_overtime_minutes']);
        $this->assertSame(960, $full['daily_overtime_minutes']);
        $this->assertSame(540, $combined['total_minutes']);
    }

    public function test_unconfigured_daily_overtime_is_null_and_empty_daily_range_is_zero(): void
    {
        $legacy = $this->calculator->summarizeEntries([]);
        $this->assertSame('weekly', $legacy['overtime_basis']);
        $this->assertNull($legacy['daily_overtime_minutes']);
        $this->assertSame(0, $legacy['overtime_minutes']);
        $daily = (new HoursCalculator(2400, 'Europe/London', 480, 'daily'))->summarizeEntries([]);
        $this->assertSame(0, $daily['daily_overtime_minutes']);
        $this->assertSame('00:00', $daily['overtime_formatted']);
    }

    public function test_daily_overtime_covers_weekends_dst_and_iso_year_boundary_without_repricing(): void
    {
        $calculator = new HoursCalculator(2400, 'Europe/London', 450, 'daily');
        $entries = [];
        foreach (['2026-03-29', '2026-10-25', '2026-12-31', '2027-01-01'] as $date) {
            $entries[] = ['work_date' => $date, 'start_time' => '00:30', 'end_time' => '09:00', 'break_minutes' => 30,
                'earnings_minor' => 12345, 'hourly_rate_minor' => 2200];
        }
        $summary = $calculator->summarizeEntries($entries);
        $this->assertSame(120, $summary['overtime_minutes']);
        $this->assertSame(49380, $summary['earnings_minor']);
        $this->assertSame('2026-W53', $summary['entries'][3]['week_key']);
        $this->assertSame(12345, $summary['entries'][0]['earnings_minor']);
    }

    public function test_daily_mode_requires_a_positive_contract(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new HoursCalculator(2400, 'Europe/London', null, 'daily');
    }
}
