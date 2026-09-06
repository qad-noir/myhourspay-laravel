<x-app-layout>
<x-slot name="header">Business tools · Payroll</x-slot>
<x-dashboard.page-header eyebrow="Payroll exports" title="Export approved time with a repeatable structure" description="First save the columns your payroll process expects. Exports then include only approved or locked timesheets." />
<x-tools.navigation area="business" :$access :$canPayroll />

@if($access['payroll_exports'] && $canPayroll)
<x-tools.prerequisites title="Payroll readiness" description="Both stages are required before an export can contain data." :items="[
    ['label'=>'Export profile','description'=>'Choose a file format and approved-time columns.','complete'=>$payrollProfiles->isNotEmpty(),'route'=>route('business.payroll.index'),'action'=>'Create below'],
    ['label'=>'Approved timesheet','description'=>'At least one submitted week must be approved or locked.','complete'=>$approvedTimesheetCount > 0,'route'=>route('business.timesheets.index'),'action'=>'Review timesheets'],
]" />
@endif

<section class="dashboard-panel tool-module-panel"><div class="dashboard-panel-heading"><div><p class="dashboard-eyebrow">Export profiles</p><h2>Approved time only</h2></div><span>{{ $approvedTimesheetCount }} approved or locked</span></div>
@if(!$access['payroll_exports'])
    <x-pro.locked feature="payroll exports" />
@elseif(!$canPayroll)
    <x-tools.notice icon="payroll" title="Your role cannot run payroll" description="Workspace owners, administrators and payroll roles can configure profiles and export approved time." tone="violet" />
@else
    <form method="POST" action="{{ route('business.payroll-profiles.store') }}" class="pro-inline-form business-payroll-form">@csrf<label>Profile name<input name="name" value="{{ old('name') }}" required></label><label>Format<select name="format"><option value="csv" @selected(old('format')==='csv')>CSV</option><option value="xlsx" @selected(old('format')==='xlsx')>Excel</option></select></label><fieldset class="business-options-field"><legend>Included columns</legend><p>Choose the approved-time fields to include in this payroll profile.</p><div class="business-column-picker">@foreach(['employee','email','week','regular_minutes','overtime_minutes','paid_break_minutes','unpaid_break_minutes','earnings_minor','currency'] as $column)<label class="business-check-option"><input type="checkbox" name="columns[]" value="{{ $column }}" @checked(in_array($column, old('columns', ['employee','email','week','regular_minutes','overtime_minutes']), true))><span class="business-check-option__box" aria-hidden="true"><svg viewBox="0 0 16 16"><path d="m4 8 2.5 2.5L12 5"/></svg></span><span>{{ str($column)->headline() }}</span></label>@endforeach</div></fieldset><button class="dashboard-button dashboard-button--primary">Save profile</button></form>
    @error('columns')<p class="tool-form-error">{{ $message }}</p>@enderror

    <p class="tool-payroll-help">Exports include complete approved or locked timesheets whose week begins within your selected dates.</p>
    <div class="pro-record-list tool-payroll-list">@forelse($payrollProfiles as $profile)<article><span class="pro-avatar">{{ str($profile->format)->upper() }}</span><div><strong>{{ $profile->name }}</strong><small>{{ collect($profile->columns)->map(fn($column)=>str($column)->headline())->join(', ') }}</small></div>@if($approvedTimesheetCount > 0)<form method="GET" action="{{ route('business.payroll.download',$profile) }}" class="tool-record-action"><span class="tool-payroll-period">Latest approved week · edit dates as needed</span><input type="date" name="start" value="{{ old('start', $exportStart) }}" aria-label="Export start date" required><input type="date" name="end" value="{{ old('end', $exportEnd) }}" aria-label="Export end date" required><button>Export approved time</button></form>@else<div class="tool-payroll-guidance"><strong class="tool-record-status">Awaiting approved time</strong><p>Log hours → Submit the week → Approve &amp; lock → Export</p><a wire:navigate href="{{ route('hours.index') }}">Log hours</a><a wire:navigate href="{{ route('business.timesheets.index') }}">{{ $canReview ? 'Review timesheets' : 'View timesheets' }}</a>@unless($canReview)<small>Ask a workspace owner, administrator or manager to approve the submitted week.</small>@endunless</div>@endif</article>@empty<x-tools.empty-state icon="payroll" title="No export profile" description="Save the columns your payroll system expects before choosing a date range." />@endforelse</div>
    @error('start')<p class="tool-form-error">{{ $message }}</p>@enderror
    @error('end')<p class="tool-form-error">{{ $message }}</p>@enderror
    @error('payroll')<p class="tool-form-error">{{ $message }}</p>@enderror
@endif</section>
</x-app-layout>
