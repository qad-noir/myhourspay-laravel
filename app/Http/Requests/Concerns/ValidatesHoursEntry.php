<?php

namespace App\Http\Requests\Concerns;

use App\Models\Timesheet;
use App\Services\CurrentWorkspace;
use App\Services\HoursCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

trait ValidatesHoursEntry
{
    protected function prepareForValidation(): void
    {
        if (! $this->filled('break_type')) {
            $entry = $this->route('hoursEntry');
            $default = $entry?->break_type
                ?? app(CurrentWorkspace::class)->for($this->user())->default_break_type
                ?? 'unpaid';
            $this->merge(['break_type' => $default]);
        }
    }

    protected function entryRules(): array
    {
        return [
            'work_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'break_minutes' => ['required', 'integer', 'min:0', 'max:'.config('hours.maximum_break_minutes')],
            'break_type' => ['required', 'in:paid,unpaid'],
            'notes' => ['nullable', 'string', 'max:'.config('hours.maximum_notes_length')],
            'project_id' => ['nullable', Rule::exists('projects', 'id')->where('workspace_id', app(CurrentWorkspace::class)->for($this->user())->id)],
            'billable' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['work_date', 'start_time', 'end_time', 'break_minutes'])) {
                return;
            }

            try {
                app(HoursCalculator::class)->validateDate((string) $this->input('work_date'));
                app(HoursCalculator::class)->calculateNetMinutes(
                    (string) $this->input('start_time'),
                    (string) $this->input('end_time'),
                    (int) $this->input('break_minutes'),
                    (string) $this->input('break_type'),
                );
            } catch (InvalidArgumentException $exception) {
                $validator->errors()->add('end_time', $exception->getMessage());
            }

            $weekStart = CarbonImmutable::parse((string) $this->input('work_date'))->startOfWeek()->toDateString();
            $locked = Timesheet::query()->where('workspace_id', app(CurrentWorkspace::class)->for($this->user())->id)->where('user_id', $this->user()->id)->whereDate('week_start', $weekStart)->whereIn('status', ['approved', 'locked'])->exists();
            if ($locked) {
                $validator->errors()->add('work_date', 'This week belongs to an approved timesheet. A manager must reopen it before hours can change.');
            }
        }];
    }
}
