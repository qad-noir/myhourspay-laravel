<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HoursEntry;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AdminAudit;
use App\Services\EmailVerificationCodeService;
use App\Services\HoursCalculator;
use App\Services\OperationalIncidentRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class AdminManagementController extends Controller
{
    public function __construct(private readonly AdminAudit $audit) {}

    public function createUser(): View { return view('admin.users.create'); }

    public function storeUser(Request $request, OperationalIncidentRecorder $incidents): RedirectResponse
    {
        $data = $request->validate(['name' => ['required','string','min:2','max:255'], 'email' => ['required','email','max:255','unique:users,email']]);
        $user = User::query()->create([...$data, 'password' => Hash::make(Str::random(64))]);
        $this->audit->record($request, 'user.created', $user, [], $user->only(['name','email']));
        try {
            $status = Password::sendResetLink(['email' => $user->email]);
            if ($status !== Password::RESET_LINK_SENT) {
                throw new \RuntimeException(__($status));
            }
        } catch (Throwable $exception) {
            $incident = $incidents->record('admin.user_setup_email_failed', $exception, ['name' => $user->name, 'email' => $user->email]);
            return to_route('admin.users.show', $user)->with('status', "User created, but setup email failed. Incident {$incident->reference}.");
        }
        return to_route('admin.users.show', $user)->with('status', 'User created and password setup email sent.');
    }

    public function verify(Request $request, User $user): RedirectResponse
    {
        $user->forceFill(['email_verified_at' => $user->email_verified_at ? null : now()])->save();
        $this->audit->record($request, $user->email_verified_at ? 'user.verified' : 'user.verification_revoked', $user);
        return back()->with('status', $user->email_verified_at ? 'User verified.' : 'Verification revoked.');
    }

    public function resendVerification(Request $request, User $user, EmailVerificationCodeService $codes, OperationalIncidentRecorder $incidents): RedirectResponse
    {
        abort_if($user->email_verified_at, 422, 'This user is already verified.');
        try {
            $codes->issue($user);
        } catch (Throwable $exception) {
            $incident = $incidents->record('admin.verification_email_failed', $exception, ['name' => $user->name, 'email' => $user->email]);

            return back()->with('status', "The verification email could not be sent. Incident {$incident->reference}.");
        }
        $this->audit->record($request, 'user.verification_resent', $user);
        return back()->with('status', 'Verification code resent.');
    }

    public function resetWorkspace(Request $request, User $user): RedirectResponse
    {
        $user->forceFill(['current_workspace_id' => null, 'workspace_onboarding_reset_at' => now()])->save();
        $this->audit->record($request, 'user.workspace_onboarding_reset', $user);
        return back()->with('status', 'Workspace setup will be required on the user’s next request.');
    }

    public function deleteUser(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user), 422, 'You cannot delete your own account.');
        DB::transaction(function () use ($request, $user): void {
            $this->audit->record($request, 'user.trashed', $user);
            $user->hoursEntries()->delete(); $user->ownedWorkspaces()->each(fn (Workspace $workspace) => $this->trashWorkspaceData($workspace));
            DB::table('sessions')->where('user_id', $user->id)->delete(); $user->delete();
        });
        return to_route('admin.users.index')->with('status', 'User moved to trash.');
    }

    public function restoreUser(Request $request, int $user): RedirectResponse
    {
        $model = User::onlyTrashed()->findOrFail($user); $model->restore();
        $model->ownedWorkspaces()->onlyTrashed()->get()->each(function (Workspace $workspace): void { $workspace->restore(); $workspace->hoursEntries()->onlyTrashed()->restore(); });
        $model->hoursEntries()->onlyTrashed()->restore(); $this->audit->record($request, 'user.restored', $model);
        return back()->with('status', 'User and owned data restored.');
    }

    public function forceDeleteUser(Request $request, int $user): RedirectResponse
    {
        $model = User::onlyTrashed()->findOrFail($user); abort_if($request->user()->is($model), 422);
        $this->audit->record($request, 'user.permanently_deleted', $model, $model->only(['name','email']));
        $model->ownedWorkspaces()->withTrashed()->get()->each(fn (Workspace $workspace) => $workspace->forceDelete()); $model->hoursEntries()->withTrashed()->forceDelete(); $model->forceDelete();
        return back()->with('status', 'User permanently deleted.');
    }

    public function createWorkspace(): View { return view('admin.workspaces.create', ['users' => User::query()->orderBy('name')->get()]); }

    public function storeWorkspace(Request $request): RedirectResponse
    {
        $data = $this->workspaceData($request); $owner = User::query()->findOrFail($data['owner_id']);
        $workspace = DB::transaction(function () use ($data, $owner): Workspace { $workspace = $owner->ownedWorkspaces()->create($this->workspaceValues($data)); $workspace->users()->attach($owner, ['role'=>'owner','position'=>$data['position']]); return $workspace; });
        $this->audit->record($request, 'workspace.created', $workspace); return to_route('admin.workspaces.show', $workspace)->with('status', 'Workspace created.');
    }

    public function deleteWorkspace(Request $request, Workspace $workspace): RedirectResponse
    {
        DB::transaction(function () use ($request, $workspace): void { $this->audit->record($request, 'workspace.trashed', $workspace); $this->trashWorkspaceData($workspace); });
        return to_route('admin.workspaces.index')->with('status', 'Workspace moved to trash.');
    }

    public function restoreWorkspace(Request $request, int $workspace): RedirectResponse
    {
        $model=Workspace::onlyTrashed()->findOrFail($workspace); $model->restore(); $model->hoursEntries()->onlyTrashed()->restore(); $this->audit->record($request,'workspace.restored',$model); return back()->with('status','Workspace restored.');
    }

    public function forceDeleteWorkspace(Request $request, int $workspace): RedirectResponse
    {
        $model=Workspace::onlyTrashed()->findOrFail($workspace); $this->audit->record($request,'workspace.permanently_deleted',$model,['name'=>$model->name]); $model->hoursEntries()->withTrashed()->forceDelete(); $model->forceDelete(); return back()->with('status','Workspace permanently deleted.');
    }

    public function hours(): View { return view('admin.hours.index'); }
    public function createHours(): View { return view('admin.hours.form', ['entry'=>null,'users'=>User::with('workspaces')->orderBy('name')->get()]); }
    public function editHours(HoursEntry $hoursEntry): View { return view('admin.hours.form', ['entry'=>$hoursEntry,'users'=>User::with('workspaces')->orderBy('name')->get()]); }
    public function storeHours(Request $request): RedirectResponse { $data=$this->hoursData($request); $entry=HoursEntry::query()->create($data); $this->audit->record($request,'hours.created',$entry); return to_route('admin.hours.index')->with('status','Hours entry created.'); }
    public function updateHours(Request $request, HoursEntry $hoursEntry): RedirectResponse { $data=$this->hoursData($request,$hoursEntry); $before=$hoursEntry->toArray(); $hoursEntry->update($data); $this->audit->record($request,'hours.updated',$hoursEntry,$before,$hoursEntry->toArray()); return to_route('admin.hours.index')->with('status','Hours entry updated.'); }
    public function deleteHours(Request $request, HoursEntry $hoursEntry): RedirectResponse { $this->audit->record($request,'hours.trashed',$hoursEntry); $hoursEntry->delete(); return back()->with('status','Hours entry moved to trash.'); }
    public function restoreHours(Request $request,int $hoursEntry): RedirectResponse { $entry=HoursEntry::onlyTrashed()->findOrFail($hoursEntry); $entry->restore(); $this->audit->record($request,'hours.restored',$entry); return back()->with('status','Hours entry restored.'); }
    public function forceDeleteHours(Request $request,int $hoursEntry): RedirectResponse { $entry=HoursEntry::onlyTrashed()->findOrFail($hoursEntry); $this->audit->record($request,'hours.permanently_deleted',$entry); $entry->forceDelete(); return back()->with('status','Hours entry permanently deleted.'); }
    public function trash(): View { return view('admin.trash'); }

    private function trashWorkspaceData(Workspace $workspace): void { $workspace->hoursEntries()->delete(); User::query()->where('current_workspace_id',$workspace->id)->update(['current_workspace_id'=>null]); $workspace->delete(); }
    private function workspaceData(Request $request): array { return $request->validate(['owner_id'=>['required','exists:users,id'],'position'=>['required','string','min:3','max:100'],'name'=>['required','string','min:3','max:100',Rule::unique('workspaces')->where('owner_id',$request->input('owner_id'))],'default_break_type'=>['required','in:paid,unpaid'],'default_break_minutes'=>['required','integer','min:0','max:1439'],'weekly_target_hours'=>['required','numeric','min:1','max:168']]); }
    private function workspaceValues(array $data): array { return ['name'=>trim($data['name']),'default_break_type'=>$data['default_break_type'],'default_break_minutes'=>$data['default_break_minutes'],'weekly_target_minutes'=>(int)round($data['weekly_target_hours']*60)]; }
    private function hoursData(Request $request, ?HoursEntry $entry=null): array
    {
        $data=$request->validate(['user_id'=>['required','exists:users,id'],'workspace_id'=>['required','exists:workspaces,id'],'work_date'=>['required','date_format:Y-m-d',Rule::unique('hours_entries')->where(fn($q)=>$q->where('user_id',$request->input('user_id'))->where('workspace_id',$request->input('workspace_id')))->ignore($entry)],'start_time'=>['required','date_format:H:i'],'end_time'=>['required','date_format:H:i','after:start_time'],'break_type'=>['required','in:paid,unpaid'],'break_minutes'=>['required','integer','min:0','max:1439'],'notes'=>['nullable','string','max:2000']]);
        abort_unless(User::find($data['user_id'])->workspaces()->whereKey($data['workspace_id'])->exists(),422,'User is not a member of that workspace.');
        try {
            app(HoursCalculator::class)->calculateNetMinutes($data['start_time'], $data['end_time'], (int) $data['break_minutes'], $data['break_type']);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['break_minutes' => $exception->getMessage()]);
        }

        return $data;
    }
}
