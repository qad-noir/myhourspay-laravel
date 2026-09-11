<x-app-layout>
    <x-slot name="header">Overview</x-slot>
    @php
        $marketingReady = \Illuminate\Support\Facades\Schema::hasTable('marketing_preferences');
        $marketingPreference = $marketingReady ? \App\Models\MarketingPreference::where('user_id', auth()->id())->first() : null;
    @endphp
    @if($marketingReady && !$marketingPreference && auth()->user()->ownedWorkspaces()->exists())
        <section class="dashboard-card marketing-invitation"><div><h2>Get more from your hours</h2><p>Optional product tips for your workspace, with occasional offers. You choose whether to subscribe.</p></div><a class="dashboard-button dashboard-button--secondary" href="{{ route('marketing.preferences') }}">Choose email preference</a><form method="POST" action="{{ route('marketing.dismiss') }}">@csrf<button class="dashboard-button dashboard-button--secondary">Not now</button></form></section>
    @endif
    <x-dashboard.page-header :eyebrow="$now->format('l, j F Y')" :title="$greeting.', '.str(auth()->user()->name)->before(' ').' 👋'" description="Here’s a clear view of your hours for this week and month.">
        <x-slot:actions><a wire:navigate href="{{ route('hours.index', ['month' => $now->format('Y-m')]) }}" class="dashboard-period">{{ $now->format('F Y') }} <span aria-hidden="true">›</span></a></x-slot:actions>
    </x-dashboard.page-header>

    @php
        $target = $calculator->weeklyTargetMinutes();
        $targetLabel = $calculator->formatHumanMinutes($target);
        $varianceSupport = $week['worked_days'] === 0 ? 'No hours recorded this week' : ($variance === 0 ? 'On target' : $calculator->formatHumanMinutes(abs($variance)).' '.($variance > 0 ? 'above' : 'below').' '.$targetLabel.' target');
        $tone = $week['worked_days'] === 0 ? 'neutral' : ($variance >= 0 ? 'positive' : 'negative');
    @endphp
    <section class="dashboard-stats" aria-label="Hours overview">
        <x-dashboard.stat-card label="This week" :value="$calculator->formatHumanMinutes($week['total_minutes'])" :support="$varianceSupport" :tone="$tone" icon="clock" />
        <x-dashboard.stat-card label="This month" :value="$calculator->formatHumanMinutes($month['total_minutes'])" :support="$month['worked_days'].' worked '.str('day')->plural($month['worked_days'])" tone="analytics" icon="calendar" />
        <x-dashboard.stat-card label="Daily average" :value="$calculator->formatHumanMinutes($month['average_minutes'])" support="Across worked days" tone="violet" icon="stopwatch" />
        <x-dashboard.stat-card label="Target variance" :value="$calculator->formatHumanMinutes($variance)" support="Weekly difference" :tone="$tone" icon="trend" />
        <x-dashboard.stat-card label="Overtime this week" :value="$calculator->formatHumanMinutes($weeklyOvertime)" :support="$weeklyOvertime > 0 ? 'Above the '.$targetLabel.' weekly target' : 'No overtime this week'" :tone="$weeklyOvertime > 0 ? 'positive' : 'neutral'" icon="trend" />
        <x-dashboard.stat-card label="Overtime this month" :value="$calculator->formatHumanMinutes($monthlyOvertime)" support="Total positive weekly excess" :tone="$monthlyOvertime > 0 ? 'positive' : 'neutral'" icon="target" />
    </section>

    <section class="dashboard-panel monthly-breaks" aria-labelledby="monthly-breaks-title">
        <div class="dashboard-panel-heading"><div><p class="dashboard-eyebrow">{{ $now->format('F Y') }}</p><h2 id="monthly-breaks-title">Monthly break summary</h2></div><span>{{ $month['break_count'] }} {{ str('break')->plural($month['break_count']) }} logged</span></div>
        <div class="monthly-breaks__grid">
            <article><span>Breaks recorded</span><strong>{{ $month['break_count'] }}</strong><small>Entries with a break</small></article>
            <article class="is-paid"><span>Paid breaks included</span><strong>{{ $calculator->formatHumanMinutes($month['paid_break_minutes']) }}</strong><small>Included in worked hours</small></article>
            <article class="is-unpaid"><span>Unpaid breaks deducted</span><strong>{{ $calculator->formatHumanMinutes($month['unpaid_break_minutes']) }}</strong><small>Removed from worked hours</small></article>
        </div>
    </section>

    <div class="dashboard-overview-grid">
        <x-dashboard.panel title="Weekly hours" :description="'Monday to Sunday · '.$targetLabel.' target'">
            <x-slot:actions><span class="weekly-total">{{ $calculator->formatHumanMinutes($week['total_minutes']) }} total</span></x-slot:actions>
            @if($week['worked_days'] === 0)
                <x-dashboard.empty-state compact><x-slot:action><button type="button" data-open-hours class="dashboard-button dashboard-button--primary">Add hours</button></x-slot:action></x-dashboard.empty-state>
            @else
                <div class="weekly-chart" role="group" aria-label="Weekly hours: {{ collect($days)->map(fn($day) => $day['label'].' '.$day['formatted'])->join(', ') }}">
                    @foreach($days as $day)
                        @php($tooltipId = 'weekly-chart-tooltip-'.$loop->index)
                        <div class="weekly-chart__day" tabindex="0" aria-describedby="{{ $tooltipId }}" aria-label="{{ $day['full_date'] }}: {{ $day['minutes'] > 0 ? $calculator->formatHumanMinutes($day['minutes']).' logged' : 'No hours logged' }}">
                            <div class="weekly-chart__value">{{ $day['minutes'] > 0 ? $calculator->formatHumanMinutes($day['minutes']) : '—' }}</div>
                            <div class="weekly-chart__track"><span style="height: {{ max(3, min(100, ($day['minutes'] / 600) * 100)) }}%" class="{{ $day['minutes'] >= 480 ? 'is-target' : '' }}"></span></div>
                            <strong>{{ $day['label'] }}</strong>
                            <div class="weekly-chart__tooltip" id="{{ $tooltipId }}" role="tooltip">
                                <strong>{{ $day['full_date'] }}</strong>
                                @if($day['minutes'] > 0)
                                    <span>{{ $calculator->formatHumanMinutes($day['minutes']) }} logged</span>
                                    <small>{{ $day['start_time'] }}–{{ $day['end_time'] }} · {{ $day['break_minutes'] }}m {{ $day['break_type'] }} break</small>
                                @else
                                    <span>No hours logged</span>
                                    <small>Add an entry from the hours calendar.</small>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="weekly-target-line"><span><i></i> Daily hours</span><span>Weekly target: {{ $targetLabel }}</span></div>
            @endif
        </x-dashboard.panel>

        <x-dashboard.panel title="Recent records" description="Your latest worked days">
            <x-slot:actions><a wire:navigate href="{{ route('hours.index') }}" class="dashboard-text-link">View calendar →</a></x-slot:actions>
            @if($recent->isEmpty())<x-dashboard.empty-state compact />@else<div class="recent-records">@foreach($recent as $entry)<a href="{{ route('hours.index', ['month' => substr($entry['work_date'], 0, 7), 'edit' => $entry['id']]) }}" aria-label="Edit hours for {{ $entry['work_date'] }}"><span class="recent-date"><strong>{{ \Carbon\CarbonImmutable::parse($entry['work_date'])->format('d') }}</strong>{{ \Carbon\CarbonImmutable::parse($entry['work_date'])->format('M') }}</span><span><strong>{{ $entry['weekday'] }}</strong>{{ $entry['start_time'] }}–{{ $entry['end_time'] }} · {{ $entry['break_minutes'] }}m {{ $entry['break_type'] }} break</span><b>{{ $calculator->formatHumanMinutes($entry['net_minutes']) }}</b></a>@endforeach</div>@endif
        </x-dashboard.panel>
    </div>
</x-app-layout>
