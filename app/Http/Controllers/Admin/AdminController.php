<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\HoursEntry;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AdminMetrics;
use App\Services\HoursCalculator;
use App\Services\SubscriptionState;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function dashboard(HoursCalculator $calculator, AdminMetrics $adminMetrics): View
    {
        $now = CarbonImmutable::now(config('hours.timezone'));
        $metrics = $adminMetrics->current($now);
        $recentUsers = User::query()->latest()->latest('id')->limit(5)->get();
        $recentAudits = AdminAuditLog::query()->with(['admin', 'target'])->latest()->latest('id')->limit(5)->get();

        return view('admin.dashboard', compact('metrics', 'recentUsers', 'recentAudits', 'calculator', 'now'));
    }

    public function users(Request $request): View
    {
        $users = User::query()->withCount(['workspaces', 'hoursEntries'])
            ->when($request->filled('search'), fn ($query) => $query->where(fn ($nested) => $nested
                ->where('name', 'like', '%'.$request->string('search')->trim().'%')
                ->orWhere('email', 'like', '%'.$request->string('search')->trim().'%')))
            ->when($request->input('status') === 'suspended', fn ($query) => $query->whereNotNull('suspended_at'))
            ->when($request->input('status') === 'active', fn ($query) => $query->whereNull('suspended_at'))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function user(User $user, HoursCalculator $calculator): View
    {
        $user->load(['subscriptions.items', 'entitlementGrants.plan', 'workspaces']);

        return view('admin.users.show', compact('user', 'calculator') + ['billing' => app(SubscriptionState::class)->summary($user)]);
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
        ]);
        $before = $user->only(['name', 'email', 'email_verified_at']);
        if (mb_strtolower($validated['email']) !== mb_strtolower($user->email)) {
            $validated['email_verified_at'] = null;
        }
        $user->forceFill($validated)->save();
        $this->audit($request, 'user.updated', $user, $before, $user->only(['name', 'email', 'email_verified_at']));

        return back()->with('status', 'User updated.');
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user), 422, 'You cannot suspend your own account.');
        $before = ['suspended_at' => $user->suspended_at?->toIso8601String()];
        $user->update(['suspended_at' => $user->suspended_at ? null : now()]);
        if ($user->suspended_at) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
        $this->audit($request, $user->suspended_at ? 'user.suspended' : 'user.reactivated', $user, $before, ['suspended_at' => $user->suspended_at?->toIso8601String()]);

        return back()->with('status', $user->suspended_at ? 'User suspended.' : 'User reactivated.');
    }

    public function workspaces(Request $request): View
    {
        $workspaces = Workspace::query()->with('owner')->withCount(['users', 'hoursEntries'])
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search')->trim().'%'))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.workspaces.index', compact('workspaces'));
    }

    public function workspace(Workspace $workspace, HoursCalculator $calculator): View
    {
        $workspace->load(['owner', 'users']);

        return view('admin.workspaces.show', compact('workspace', 'calculator'));
    }

    public function updateWorkspace(Request $request, Workspace $workspace): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:100', Rule::unique('workspaces', 'name')->where('owner_id', $workspace->owner_id)->ignore($workspace)],
            'default_break_type' => ['required', 'in:paid,unpaid'],
            'default_break_minutes' => ['required', 'integer', 'min:0', 'max:1439'],
            'weekly_target_hours' => ['required', 'numeric', 'min:1', 'max:168'],
        ]);
        $before = $workspace->only(['name', 'default_break_type', 'default_break_minutes', 'weekly_target_minutes']);
        $workspace->update([
            'name' => trim($validated['name']),
            'default_break_type' => $validated['default_break_type'],
            'default_break_minutes' => $validated['default_break_minutes'],
            'weekly_target_minutes' => (int) round($validated['weekly_target_hours'] * 60),
        ]);
        $this->audit($request, 'workspace.updated', $workspace, $before, $workspace->only(array_keys($before)));

        return back()->with('status', 'Workspace updated.');
    }

    private function audit(Request $request, string $action, Model $target, array $before, array $after): void
    {
        $log = new AdminAuditLog([
            'admin_user_id' => $request->user()->id,
            'action' => $action,
            'before' => $before,
            'after' => $after,
            'ip_address' => $request->ip(),
        ]);
        $log->target()->associate($target);
        $log->save();
    }
}
