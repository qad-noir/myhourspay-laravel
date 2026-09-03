<x-app-layout>
<x-slot name="header">Business tools · Team</x-slot>
<x-dashboard.page-header eyebrow="Team and roles" title="Give each person the right workspace access" description="Invitations establish membership. Roles decide who can manage, review or export without changing historical hours." />
<x-tools.navigation area="business" :$access :$canPayroll />

<section class="dashboard-panel tool-module-panel">
    <div class="dashboard-panel-heading"><div><p class="dashboard-eyebrow">Workspace directory</p><h2>Members and invitations</h2></div><span>{{ $members->count() }} active · your role: {{ str($role)->headline() }}</span></div>
    @if(!$access['team_members'])
        <x-pro.locked feature="team members" />
    @else
        @if($canManage)
            <form method="POST" action="{{ route('business.invitations.store') }}" class="pro-inline-form tool-invite-form">@csrf<label>Email<input type="email" name="email" value="{{ old('email') }}" required></label><label>Role<select name="role"><option value="member">Member</option><option value="manager">Manager</option><option value="payroll">Payroll</option><option value="administrator">Administrator</option></select></label><label>Position<input name="position" value="{{ old('position') }}"></label><button class="dashboard-button dashboard-button--primary">Send invitation</button></form>
        @else
            <x-tools.notice icon="team" title="Your role can view the team" description="A workspace owner or administrator manages invitations and access changes." />
        @endif

        <div class="business-member-list tool-member-list">
            @foreach($members as $member)
                <article><span class="pro-avatar">{{ str($member->name)->substr(0,1)->upper() }}</span><div><strong>{{ $member->name }}</strong><small>{{ $member->email }} · {{ $member->pivot->position ?: 'No position' }}</small></div><b>{{ str($member->pivot->role)->headline() }}</b>@if($canManage && $member->id!==$workspace->owner_id)<details class="business-actions"><summary aria-label="Actions for {{ $member->name }}"><x-dashboard.icon name="more" :size="18" /></summary><div>@if($access['roles_permissions'])<form method="POST" action="{{ route('business.members.update',$member) }}">@csrf @method('PUT')<select name="role">@foreach(['member','manager','payroll','administrator'] as $memberRole)<option value="{{ $memberRole }}" @selected($member->pivot->role===$memberRole)>{{ str($memberRole)->headline() }}</option>@endforeach</select><input name="position" value="{{ $member->pivot->position }}"><button>Save access</button></form>@endif<form method="POST" action="{{ route('business.members.destroy',$member) }}" data-confirm="Remove this member? Their historical hours will be retained.">@csrf @method('DELETE')<button class="is-danger">Remove member</button></form></div></details>@endif</article>
            @endforeach
        </div>

        @if($invitations->isNotEmpty())
            <section class="tool-subsection"><header><h3>Pending invitations</h3><span>{{ $invitations->count() }}</span></header><div class="pro-record-list">@foreach($invitations as $invitation)<article><span class="pro-avatar"><x-dashboard.icon name="pending" :size="17" /></span><div><strong>{{ $invitation->email }}</strong><small>{{ str($invitation->role)->headline() }} · expires {{ $invitation->expires_at->format('d M Y') }}</small></div><b>Pending</b></article>@endforeach</div></section>
        @endif
    @endif
</section>
</x-app-layout>
