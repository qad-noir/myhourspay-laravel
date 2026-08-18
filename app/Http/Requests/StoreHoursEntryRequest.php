<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesHoursEntry;
use App\Models\HoursEntry;
use App\Services\CurrentWorkspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHoursEntryRequest extends FormRequest
{
    use ValidatesHoursEntry;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return array_merge($this->entryRules(), [
            'work_date' => [
                'required',
                'date_format:Y-m-d',
                Rule::unique(HoursEntry::class)->where(fn ($query) => $query
                    ->where('user_id', $this->user()->id)
                    ->where('workspace_id', app(CurrentWorkspace::class)->for($this->user())->id)),
            ],
        ]);
    }
}
