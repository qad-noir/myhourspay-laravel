<?php

namespace App\Http\Controllers;

use App\Services\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HoursSettingsController extends Controller
{
    public function update(Request $request, CurrentWorkspace $current): RedirectResponse
    {
        $request->merge([
            'default_break_type' => $request->input('default_break_type', $current->for($request->user())->default_break_type ?? 'unpaid'),
        ]);
        $validated = $request->validate([
            'default_break_type' => ['required', 'in:paid,unpaid'],
            'default_break_minutes' => ['required', 'integer', 'min:0', 'max:1439'],
            'weekly_target_hours' => ['required', 'numeric', 'min:1', 'max:168'],
        ]);

        $current->for($request->user())->update([
            'default_break_type' => $validated['default_break_type'],
            'default_break_minutes' => $validated['default_break_minutes'],
            'weekly_target_minutes' => (int) round((float) $validated['weekly_target_hours'] * 60),
        ]);

        return to_route('profile.show')->with('status', 'Hours preferences updated.');
    }
}
