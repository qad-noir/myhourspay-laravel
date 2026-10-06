<?php

namespace App\Services;

use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WorkspaceOvertimeSettings
{
    public function validate(Request $request, ?Workspace $workspace = null, bool $minutes = false): array
    {
        $field = $minutes ? 'contracted_daily_minutes' : 'contracted_daily_hours';
        $rules = $minutes ? ['sometimes', 'nullable', 'integer', 'min:1', 'max:1440'] : ['sometimes', 'nullable', 'numeric', 'gt:0', 'max:24'];
        $validator = Validator::make($request->only([$field, 'overtime_basis']), [
            $field => $rules, 'overtime_basis' => ['sometimes', 'required', 'in:daily,weekly'],
        ]);
        $validator->after(function ($validator) use ($request, $workspace, $field, $minutes): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $daily = $request->exists($field) ? $request->input($field) : $workspace?->contracted_daily_minutes;
            if ($request->exists($field) && $daily !== null && ! $minutes) {
                $daily = (int) round((float) $daily * 60);
                if ($daily < 1) {
                    $validator->errors()->add($field, 'Contracted daily hours must be at least one minute.');
                }
            }
            if ($request->input('overtime_basis', $workspace?->overtime_basis ?? 'weekly') === 'daily' && $daily === null) {
                $validator->errors()->add($field, 'Enter contracted daily hours to use daily overtime.');
            }
        });
        $data = $validator->validate();
        $result = [];
        if (array_key_exists($field, $data)) {
            $result['contracted_daily_minutes'] = $data[$field] === null ? null : ($minutes ? (int) $data[$field] : (int) round((float) $data[$field] * 60));
        }
        if (isset($data['overtime_basis'])) {
            $result['overtime_basis'] = $data['overtime_basis'];
        }

        return $result;
    }

    public function version(Workspace $workspace): string
    {
        return hash('sha256', json_encode([$workspace->weekly_target_minutes, $workspace->contracted_daily_minutes, $workspace->overtime_basis ?? 'weekly']));
    }
}
