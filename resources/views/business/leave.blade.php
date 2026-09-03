<x-app-layout>
<x-slot name="header">Business tools · Leave</x-slot>
<x-dashboard.page-header eyebrow="Leave" title="Keep time away separate from worked hours" description="Workspace managers define valid leave types first. Team members can then request full or partial days." />
<x-tools.navigation area="business" :$access :$canPayroll />

<section class="dashboard-panel tool-module-panel"><div class="dashboard-panel-heading"><div><p class="dashboard-eyebrow">Leave workflow</p><h2>Types, requests and review</h2></div><span>{{ $leaveTypes->count() }} configured</span></div>
@if(!$access['leave_tracking'])<x-pro.locked feature="leave tracking" />@else
    @if($leaveTypes->isEmpty())
        @if($canManage)<x-tools.notice icon="leave" title="Create a leave type first" description="Requests need a workspace-defined category such as Annual leave or Sick leave. The request form will appear immediately after setup." tone="orange" />@else<x-tools.notice icon="leave" title="Leave is not configured yet" description="Ask a workspace owner or administrator to create the first leave type before requesting time away." tone="violet" />@endif
    @endif
    <div class="tool-form-grid tool-leave-grid">
        @if($canManage)
            <form method="POST" action="{{ route('business.leave-types.store') }}" class="pro-compact-form">@csrf<h3>Add leave type</h3><label>Name<input name="name" value="{{ old('name') }}" required></label><label>Colour<input class="business-colour-input" type="color" name="colour" value="{{ old('colour','#8268ff') }}"></label><label class="ui-switch"><input type="checkbox" role="switch" name="paid" value="1" @checked(old('paid'))><span class="ui-switch__track" aria-hidden="true"><span></span></span><span><strong>Paid leave</strong><small>Include approved leave in payroll reporting.</small></span></label><button class="dashboard-button dashboard-button--primary">Create type</button></form>
        @endif
        @if($leaveTypes->isNotEmpty())
            <form method="POST" action="{{ route('business.leave.store') }}" class="pro-compact-form">@csrf<h3>Request leave</h3><label>Type<select name="leave_type_id" required>@foreach($leaveTypes as $type)<option value="{{ $type->id }}" @selected((string)old('leave_type_id')===(string)$type->id)>{{ $type->name }}</option>@endforeach</select></label><div><label>From<input type="date" name="starts_on" value="{{ old('starts_on') }}" required></label><label>To<input type="date" name="ends_on" value="{{ old('ends_on') }}" required></label></div><label>Minutes per day <span>optional partial day</span><input type="number" name="minutes_per_day" min="1" max="1440" value="{{ old('minutes_per_day') }}"></label><label>Reason<textarea name="reason">{{ old('reason') }}</textarea></label><button class="dashboard-button dashboard-button--primary">Submit request</button></form>
        @endif
    </div>
    <div class="business-table-wrap"><table><thead><tr><th>Member</th><th>Type</th><th>Dates</th><th>Status</th><th>Review</th></tr></thead><tbody>@forelse($leaveRequests as $leave)<tr><td>{{ $leave->user->name }}</td><td><span class="tool-colour-dot" style="--dot: {{ $leave->type->colour }}"></span>{{ $leave->type->name }}</td><td>{{ $leave->starts_on->format('d M') }}–{{ $leave->ends_on->format('d M Y') }}</td><td><span class="business-status is-{{ $leave->status }}">{{ str($leave->status)->headline() }}</span></td><td>@if($canReview && $leave->status==='pending')<form method="POST" action="{{ route('business.leave.review',$leave) }}" data-confirm="Apply this leave decision?" data-confirm-tone="neutral">@csrf<select name="decision"><option value="approved">Approve</option><option value="rejected">Reject</option></select><button>Apply</button></form>@else—@endif</td></tr>@empty<tr><td colspan="5"><x-tools.empty-state icon="leave" title="No leave requests" description="Requests will appear here once a leave type has been configured." /></td></tr>@endforelse</tbody></table></div>
@endif</section>
</x-app-layout>
