<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use InvalidArgumentException;
use Traversable;

class HoursCalculator
{
    public function __construct(
        private readonly ?int $weeklyTargetMinutes = null,
        private readonly ?string $timezone = null,
    ) {}

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

    public function calculateNetMinutes(string $start, string $end, int $breakMinutes): int
    {
        if ($breakMinutes < 0) {
            throw new InvalidArgumentException('Break minutes cannot be negative.');
        }

        $gross = $this->calculateGrossMinutes($start, $end);
        if ($breakMinutes >= $gross) {
            throw new InvalidArgumentException('Break must be shorter than the shift.');
        }

        return $gross - $breakMinutes;
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
        $net = $this->calculateNetMinutes($start, $end, (int) $data['break_minutes']);

        return array_merge($data, [
            'work_date' => $date->format('Y-m-d'),
            'start_time' => $start,
            'end_time' => $end,
            'weekday' => $date->format('l'),
            'gross_minutes' => $gross,
            'gross_formatted' => $this->formatMinutes($gross),
            'net_minutes' => $net,
            'net_formatted' => $this->formatMinutes($net),
            'week_key' => $date->format('o-\WW'),
            'week_number' => (int) $date->format('W'),
            'week_start' => $date->startOfWeek()->format('Y-m-d'),
            'week_end' => $date->endOfWeek()->format('Y-m-d'),
        ]);
    }

    public function summarizeEntries(iterable $entries, ?string $rangeStart = null, ?string $rangeEnd = null): array
    {
        $items = [];
        $weeks = [];
        $total = 0;

        foreach ($entries instanceof Traversable ? iterator_to_array($entries) : $entries as $entry) {
            $item = $this->enrichEntry($entry);
            $items[] = $item;
            $total += $item['net_minutes'];
            $weeks[$item['week_key']] ??= [
                'key' => $item['week_key'],
                'number' => $item['week_number'],
                'start' => $item['week_start'],
                'end' => $item['week_end'],
                'minutes' => 0,
            ];
            $weeks[$item['week_key']]['minutes'] += $item['net_minutes'];
        }

        foreach ($weeks as &$week) {
            $week['formatted'] = $this->formatMinutes($week['minutes']);
            $week['target_minutes'] = $this->target();
            $week['target_formatted'] = $this->formatMinutes($this->target());
            $week['variance_minutes'] = $week['minutes'] - $this->target();
            $week['variance_formatted'] = $this->formatSignedMinutes($week['variance_minutes']);
            $week['partial'] = $rangeStart !== null && $rangeEnd !== null
                && ($rangeStart > $week['start'] || $rangeEnd < $week['end']);
        }
        unset($week);

        foreach ($items as &$item) {
            $item['weekly_total'] = $weeks[$item['week_key']]['formatted'];
            $item['weekly_variance'] = $weeks[$item['week_key']]['variance_formatted'];
            $item['partial_week'] = $weeks[$item['week_key']]['partial'];
        }
        unset($item);

        return [
            'entries' => $items,
            'weeks' => array_values($weeks),
            'total_minutes' => $total,
            'total_formatted' => $this->formatMinutes($total),
            'worked_days' => count($items),
            'average_minutes' => count($items) > 0 ? (int) round($total / count($items)) : 0,
            'average_formatted' => $this->formatMinutes(count($items) > 0 ? (int) round($total / count($items)) : 0),
        ];
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
