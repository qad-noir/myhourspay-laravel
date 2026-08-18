<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function onboarding(Request $request, CurrentWorkspace $current): View|RedirectResponse
    {
        if ($current->existsFor($request->user())) {
            return to_route('dashboard');
        }

        return view('workspaces.form', ['onboarding' => true]);
    }

    public function create(): View
    {
        return view('workspaces.form', ['onboarding' => false]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'name' => trim((string) $request->input('name')),
            'position' => trim((string) $request->input('position')),
        ]);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'position' => ['required', 'string', 'max:100'],
            'default_break_minutes' => ['required', 'integer', 'min:0', 'max:1439'],
            'weekly_target_hours' => ['required', 'numeric', 'min:1', 'max:168'],
        ]);
        DB::transaction(function () use ($request, $validated): void {
            $user = $request->user();
            $user->refresh();
            $firstWorkspace = ! $user->workspaces()->exists();
            $workspace = $user->ownedWorkspaces()->create([
                'name' => $validated['name'],
                'default_break_minutes' => $validated['default_break_minutes'],
                'weekly_target_minutes' => (int) round((float) $validated['weekly_target_hours'] * 60),
            ]);
            $workspace->users()->attach($user->id, ['role' => 'owner', 'position' => $validated['position']]);
            if ($firstWorkspace) {
                $user->hoursEntries()->whereNull('workspace_id')->update(['workspace_id' => $workspace->id]);
            }
            $user->forceFill(['current_workspace_id' => $workspace->id])->save();
        });

        return to_route('dashboard')->with('status', 'Workspace created.');
    }

    public function switch(Request $request, Workspace $workspace): RedirectResponse
    {
        abort_unless($request->user()->workspaces()->whereKey($workspace->id)->exists(), 403);
        $request->user()->forceFill(['current_workspace_id' => $workspace->id])->save();

        return to_route('dashboard')->with('status', "Switched to {$workspace->name}.");
    }
}
