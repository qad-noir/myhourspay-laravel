<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HoursSettingsController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'default_break_minutes' => ['required', 'integer', 'min:0', 'max:1439'],
            'weekly_target_hours' => ['required', 'numeric', 'min:1', 'max:168'],
        ]);

        $request->user()->update([
            'default_break_minutes' => $validated['default_break_minutes'],
            'weekly_target_minutes' => (int) round((float) $validated['weekly_target_hours'] * 60),
        ]);

        return to_route('profile.show')->with('status', 'Hours preferences updated.');
    }
}
