<?php

namespace App\Http\Requests\Concerns;

use App\Services\HoursCalculator;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

trait ValidatesHoursEntry
{
    protected function entryRules(): array
    {
        return [
            'work_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'break_minutes' => ['required', 'integer', 'min:0', 'max:'.config('hours.maximum_break_minutes')],
            'notes' => ['nullable', 'string', 'max:'.config('hours.maximum_notes_length')],
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
                );
            } catch (InvalidArgumentException $exception) {
                $validator->errors()->add('end_time', $exception->getMessage());
            }
        }];
    }
}
