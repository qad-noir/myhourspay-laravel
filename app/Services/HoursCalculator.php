<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use InvalidArgumentException;

class HoursCalculator
{
    public function __construct(
        private readonly ?int $weeklyTargetMinutes = null,
        private readonly ?string $timezone = null,
        private readonly ?int $contractedDailyMinutes = null,
        private readonly string $overtimeBasis = 'weekly',
    ) {
        if (! in_array($overtimeBasis, ['daily', 'weekly'], true)
            || ($contractedDailyMinutes !== null && ($contractedDailyMinutes < 1 || $contractedDailyMinutes > 1440))
            || ($overtimeBasis === 'daily' && $contractedDailyMinutes === null)) {
            throw new InvalidArgumentException('Daily overtime requires valid contracted daily hours.');
        }
    }

    public function forUser(User $user): self
    {
        return new self($user->weekly_target_minutes ?? (int) config('hours.weekly_target_minutes'), $this->timezone());
    }

    public function forWorkspace(Workspace $workspace): self
    {
        return new self($workspace->weekly_target_minutes ?? (int) config('hours.weekly_target_minutes'), $this->timezone(), $workspace->contracted_daily_minutes, $workspace->overtime_basis ?? 'weekly');
    }

    public function weeklyTargetMinutes(): int
    {
        return $this->target();
    }

    public function overtimeBasis(): string
    {
        return $this->overtimeBasis;
    }

    public function contractedDailyMinutes(): ?int
    {
        return $this->contractedDailyMinutes;
    }

    public function overtimeDescription(): string
    {
        return $this->overtimeBasis === 'daily'
            ? 'Daily excess above '.$this->formatHumanMinutes($this->contractedDailyMinutes).' contracted hours'
            : 'Total positive weekly excess';
    }

    public function calculateGrossMinutes(string $start, string $end): int
    {
        $startMinutes = $this->clockMinutes($start);
        $endMinutes = $this->clockMinutes($end);
        $gross = $endMinutes - $startMinutes;

        if ($gross <= 0) {
            throw new InvalidArgumentException('End time must be later than start time. Overnight shifts are not supported.');
        }

        return $gross;
    }

    public function calculateNetMinutes(string $start, string $end, int $breakMinutes, string $breakType = 'unpaid'): int
    {
        if ($breakMinutes < 0) {
            throw new InvalidArgumentException('Break minutes cannot be negative.');
        }

        $gross = $this->calculateGrossMinutes($start, $end);
        if ($breakMinutes >= $gross) {
            throw new InvalidArgumentException('Break must be shorter than the shift.');
        }

        if (! in_array($breakType, ['paid', 'unpaid'], true)) {
            throw new InvalidArgumentException('Choose a valid break type.');
        }

        return $breakType === 'paid' ? $gross : $gross - $breakMinutes;
    }

    public function validateDate(string $date): void
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Enter a valid work date.');
        }
    }

    public function enrichEntry(array|object $entry): array
    {
        $data = is_array($entry) ? $entry : $entry->toArray();
        $date = $data['work_date'] instanceof \DateTimeInterface
            ? CarbonImmutable::instance($data['work_date'])
            : CarbonImmutable::parse((string) $data['work_date'], $this->timezone());
        $start = substr((string) $data['start_time'], 0, 5);
        $end = substr((string) $data['end_time'], 0, 5);
        $gross = $this->calculateGrossMinutes($start, $end);
        $breakType = (string) ($data['break_type'] ?? 'unpaid');
        $net = $this->calculateNetMinutes($start, $end, (int) $data['break_minutes'], $breakType);

        return array_merge($data, [
            'work_date' => $date->format('Y-m-d'),
            'start_time' => $start,
            'end_time' => $end,
            'weekday' => $date->format('l'),
            'gross_minutes' => $gross,
            'gross_formatted' => $this->formatMinutes($gross),
            'net_minutes' => $net,
            'net_formatted' => $this->formatMinutes($net),
            'daily_overtime_minutes' => $this->contractedDailyMinutes === null ? null : max(0, $net - $this->contractedDailyMinutes),
            'break_type' => $breakType,
            'week_key' => $date->format('o-\WW'),
            'week_number' => (int) $date->format('W'),
            'week_start' => $date->startOfWeek()->format('Y-m-d'),
            'week_end' => $date->endOfWeek()->format('Y-m-d'),
        ]);
    }

    public function summarizeEntries(iterable $entries, ?string $rangeStart = null, ?string $rangeEnd = null, bool $retainEntries = true): array
    {
        $items = [];
        $weeks = [];
        $days = [];
        $total = 0;
        $count = 0;
        $earnings = 0;
        $breakCount = 0;
        $paidBreakMinutes = 0;
        $unpaidBreakMinutes = 0;

        foreach ($entries as $entry) {
            $item = $this->enrichEntry($entry);
            if ($retainEntries) {
                $items[] = $item;
            }
            $count++;
            $earnings += $item['earnings_minor'] ?? 0;
            $total += $item['net_minutes'];
            if ((int) $item['break_minutes'] > 0) {
                $breakCount++;
                if ($item['break_type'] === 'paid') {
                    $paidBreakMinutes += (int) $item['break_minutes'];
                } else {
                    $unpaidBreakMinutes += (int) $item['break_minutes'];
                }
            }
            $weeks[$item['week_key']] ??= [
                'key' => $item['week_key'],
                'number' => $item['week_number'],
                'start' => $item['week_start'],
                'end' => $item['week_end'],
                'minutes' => 0,
            ];
            $weeks[$item['week_key']]['minutes'] += $item['net_minutes'];
            $days[$item['work_date']] ??= ['minutes' => 0, 'week_key' => $item['week_key']];
            $days[$item['work_date']]['minutes'] += $item['net_minutes'];
        }

        foreach ($weeks as &$week) {
            $week['daily_overtime_minutes'] = $this->contractedDailyMinutes === null ? null : 0;
        }
        unset($week);
        foreach ($days as $day) {
            if ($this->contractedDailyMinutes !== null) {
                $weeks[$day['week_key']]['daily_overtime_minutes'] += max(0, $day['minutes'] - $this->contractedDailyMinutes);
            }
        }

        foreach ($weeks as &$week) {
            $week['formatted'] = $this->formatMinutes($week['minutes']);
            $week['target_minutes'] = $this->target();
            $week['target_formatted'] = $this->formatMinutes($this->target());
            $week['variance_minutes'] = $week['minutes'] - $this->target();
            $week['variance_formatted'] = $this->formatSignedMinutes($week['variance_minutes']);
            $week['weekly_overtime_minutes'] = max(0, $week['variance_minutes']);
            $week['overtime_minutes'] = $this->overtimeBasis === 'daily' ? $week['daily_overtime_minutes'] : $week['weekly_overtime_minutes'];
            $week['overtime_formatted'] = $this->formatMinutes($week['overtime_minutes']);
            $week['partial'] = $rangeStart !== null && $rangeEnd !== null
                && ($rangeStart > $week['start'] || $rangeEnd < $week['end']);
        }
        unset($week);

        foreach ($items as &$item) {
            $item['weekly_total'] = $weeks[$item['week_key']]['formatted'];
            $item['weekly_variance'] = $weeks[$item['week_key']]['variance_formatted'];
            $item['partial_week'] = $weeks[$item['week_key']]['partial'];
            $item['daily_overtime_minutes'] = $this->contractedDailyMinutes === null ? null : max(0, $days[$item['work_date']]['minutes'] - $this->contractedDailyMinutes);
            $item['weekly_overtime_minutes'] = $weeks[$item['week_key']]['weekly_overtime_minutes'];
            $item['weekly_overtime_formatted'] = $this->formatMinutes($item['weekly_overtime_minutes']);
            $item['report_overtime_formatted'] = $this->formatMinutes($this->overtimeBasis === 'daily' ? $item['daily_overtime_minutes'] : $item['weekly_overtime_minutes']);
        }
        unset($item);

        return [
            'entries' => $items,
            'weeks' => array_values($weeks),
            'total_minutes' => $total,
            'total_formatted' => $this->formatMinutes($total),
            'worked_days' => $count,
            'earnings_minor' => $earnings,
            'average_minutes' => $count > 0 ? (int) round($total / $count) : 0,
            'average_formatted' => $this->formatMinutes($count > 0 ? (int) round($total / $count) : 0),
            'break_count' => $breakCount,
            'paid_break_minutes' => $paidBreakMinutes,
            'paid_break_formatted' => $this->formatMinutes($paidBreakMinutes),
            'unpaid_break_minutes' => $unpaidBreakMinutes,
            'unpaid_break_formatted' => $this->formatMinutes($unpaidBreakMinutes),
            'overtime_basis' => $this->overtimeBasis,
            'contracted_daily_minutes' => $this->contractedDailyMinutes,
            'daily_overtime_minutes' => $this->contractedDailyMinutes === null ? null : array_sum(array_column($weeks, 'daily_overtime_minutes')),
            'weekly_overtime_minutes' => array_sum(array_column($weeks, 'weekly_overtime_minutes')),
            'overtime_minutes' => array_sum(array_column($weeks, 'overtime_minutes')),
            'overtime_formatted' => $this->formatMinutes(array_sum(array_column($weeks, 'overtime_minutes'))),
        ];
    }

    /** Preserve the legacy full-week comparison while daily overtime stays within the exact period. */
    public function withFullWeekOvertime(array $period, array $fullWeeks): array
    {
        $period['weeks'] = $fullWeeks['weeks'];
        $period['weekly_overtime_minutes'] = $fullWeeks['weekly_overtime_minutes'];
        $period['overtime_minutes'] = $this->overtimeBasis === 'daily' ? $period['daily_overtime_minutes'] : $period['weekly_overtime_minutes'];
        $period['overtime_formatted'] = $this->formatMinutes($period['overtime_minutes']);

        return $period;
    }

    /** Read-only calculation for stored payroll projections; never reprices or saves entries. */
    public function overtimeFromNetEntries(iterable $entries): int
    {
        $totals = [];
        foreach ($entries as $entry) {
            $date = CarbonImmutable::parse($entry['work_date'], $this->timezone());
            $key = $this->overtimeBasis === 'daily' ? $date->toDateString() : $date->format('o-\WW');
            $totals[$key] = ($totals[$key] ?? 0) + (int) $entry['net_minutes'];
        }
        $target = $this->overtimeBasis === 'daily' ? $this->contractedDailyMinutes : $this->target();

        return array_sum(array_map(fn (int $minutes): int => max(0, $minutes - $target), $totals));
    }

    public function formatMinutes(int $minutes): string
    {
        $minutes = abs($minutes);

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    public function formatSignedMinutes(int $minutes): string
    {
        return ($minutes >= 0 ? '+' : '-').$this->formatMinutes($minutes);
    }

    public function formatHumanMinutes(int $minutes): string
    {
        $sign = $minutes < 0 ? '-' : '';
        $minutes = abs($minutes);
        $hours = intdiv($minutes, 60);
        $remainder = $minutes % 60;

        if ($hours === 0) {
            return $sign.$remainder.'m';
        }

        return $sign.$hours.'h '.str_pad((string) $remainder, 2, '0', STR_PAD_LEFT).'m';
    }

    private function clockMinutes(string $value): int
    {
        if (! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value)) {
            throw new InvalidArgumentException('Enter a valid 24-hour time in HH:MM format.');
        }

        [$hours, $minutes] = array_map('intval', explode(':', $value));

        return $hours * 60 + $minutes;
    }

    private function target(): int
    {
        return $this->weeklyTargetMinutes ?? (int) config('hours.weekly_target_minutes');
    }

    private function timezone(): string
    {
        return $this->timezone ?? (string) config('hours.timezone');
    }
}
