<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\CurrentWorkspace;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function onboarding(Request $request, CurrentWorkspace $current): View|RedirectResponse
    {
        if (! $request->user()->workspace_onboarding_reset_at && $current->existsFor($request->user())) {
            return to_route('dashboard');
        }

        return view('workspaces.form', ['onboarding' => true]);
    }

    public function create(): View
    {
        return view('workspaces.form', ['onboarding' => false]);
    }

    public function availability(Request $request): JsonResponse
    {
        $name = trim((string) $request->query('name'));
        $valid = mb_strlen($name) >= 3 && mb_strlen($name) <= 100;
        $available = $valid && ! $this->nameTaken($request, $name);

        return response()->json([
            'available' => $available,
            'message' => $valid ? ($available ? "{$name} is available" : "{$name} is taken") : null,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'name' => trim((string) $request->input('name')),
            'position' => trim((string) $request->input('position')),
            'default_break_type' => $request->input('default_break_type', 'unpaid'),
        ]);
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:100', function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                if ($this->nameTaken($request, (string) $value)) {
                    $fail('You already have a workspace with this name.');
                }
            }],
            'position' => ['required', 'string', 'min:3', 'max:100'],
            'default_break_type' => ['required', 'in:paid,unpaid'],
            'default_break_minutes' => ['required', 'integer', 'min:0', 'max:1439'],
            'weekly_target_hours' => ['required', 'numeric', 'min:1', 'max:168'],
        ]);
        try {
            DB::transaction(function () use ($request, $validated): void {
                $user = $request->user();
                $user->refresh();
                $firstWorkspace = ! $user->workspaces()->exists();
                $workspace = $user->ownedWorkspaces()->create([
                    'name' => $validated['name'],
                    'default_break_type' => $validated['default_break_type'],
                    'default_break_minutes' => $validated['default_break_minutes'],
                    'weekly_target_minutes' => (int) round((float) $validated['weekly_target_hours'] * 60),
                ]);
                $workspace->users()->attach($user->id, ['role' => 'owner', 'position' => $validated['position']]);
                if ($firstWorkspace) {
                    $user->hoursEntries()->whereNull('workspace_id')->update(['workspace_id' => $workspace->id]);
                }
                $user->forceFill(['current_workspace_id' => $workspace->id, 'workspace_onboarding_reset_at' => null])->save();
            });
        } catch (UniqueConstraintViolationException) {
            return back()->withInput()->withErrors(['name' => 'You already have a workspace with this name.']);
        }

        return to_route('dashboard')->with('status', 'Workspace created.');
    }

    public function switch(Request $request, Workspace $workspace): RedirectResponse
    {
        abort_unless($request->user()->workspaces()->whereKey($workspace->id)->exists(), 403);
        $request->user()->forceFill(['current_workspace_id' => $workspace->id])->save();

        return to_route('dashboard')->with('status', "Switched to {$workspace->name}.");
    }

    private function nameTaken(Request $request, string $name): bool
    {
        return $request->user()->workspaces()
            ->whereRaw('LOWER(TRIM(workspaces.name)) = ?', [mb_strtolower(trim($name))])
            ->exists();
    }
}
