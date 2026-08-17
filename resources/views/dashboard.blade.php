<x-app-layout>
    <x-slot name="header">Overview</x-slot>
    <x-dashboard.page-header :eyebrow="$now->format('l, j F Y')" :title="$greeting.', '.str(auth()->user()->name)->before(' ').' 👋'" description="Here’s a clear view of your hours for this week and month.">
        <x-slot:actions><a href="{{ route('hours.index', ['month' => $now->format('Y-m')]) }}" class="dashboard-period">{{ $now->format('F Y') }} <span aria-hidden="true">›</span></a></x-slot:actions>
    </x-dashboard.page-header>

    @php
        $calculator = app(App\Services\HoursCalculator::class);
        $target = (int) config('hours.weekly_target_minutes');
        $varianceSupport = $week['worked_days'] === 0 ? 'No hours recorded this week' : ($variance === 0 ? 'On target' : $calculator->formatHumanMinutes(abs($variance)).' '.($variance > 0 ? 'above' : 'below').' 40h target');
        $tone = $week['worked_days'] === 0 ? 'neutral' : ($variance >= 0 ? 'positive' : 'negative');
    @endphp
    <section class="dashboard-stats" aria-label="Hours overview">
        <x-dashboard.stat-card label="This week" :value="$calculator->formatHumanMinutes($week['total_minutes'])" :support="$varianceSupport" :tone="$tone" icon="◷" />
        <x-dashboard.stat-card label="This month" :value="$calculator->formatHumanMinutes($month['total_minutes'])" :support="$month['worked_days'].' worked '.str('day')->plural($month['worked_days'])" tone="analytics" icon="▦" />
        <x-dashboard.stat-card label="Daily average" :value="$calculator->formatHumanMinutes($month['average_minutes'])" support="Across worked days" tone="violet" icon="≈" />
        <x-dashboard.stat-card label="Target variance" :value="$calculator->formatHumanMinutes($variance)" support="Weekly difference" :tone="$tone" icon="↗" />
    </section>

    <div class="dashboard-overview-grid">
        <x-dashboard.panel title="Weekly hours" description="Monday to Sunday · 40-hour target">
            <x-slot:actions><span class="weekly-total">{{ $calculator->formatHumanMinutes($week['total_minutes']) }} total</span></x-slot:actions>
            @if($week['worked_days'] === 0)
                <x-dashboard.empty-state compact><x-slot:action><a href="{{ route('hours.index', ['add' => 1]) }}" class="dashboard-button dashboard-button--primary">Add hours</a></x-slot:action></x-dashboard.empty-state>
            @else
                <div class="weekly-chart" role="img" aria-label="Weekly hours: {{ collect($days)->map(fn($day) => $day['label'].' '.$day['formatted'])->join(', ') }}">
                    @foreach($days as $day)<div class="weekly-chart__day"><div class="weekly-chart__value">{{ $day['minutes'] > 0 ? $calculator->formatHumanMinutes($day['minutes']) : '—' }}</div><div class="weekly-chart__track"><span style="height: {{ max(3, min(100, ($day['minutes'] / 600) * 100)) }}%" class="{{ $day['minutes'] >= 480 ? 'is-target' : '' }}"></span></div><strong>{{ $day['label'] }}</strong></div>@endforeach
                </div>
                <div class="weekly-target-line"><span><i></i> Daily hours</span><span>Weekly target: 40h 00m</span></div>
            @endif
        </x-dashboard.panel>

        <x-dashboard.panel title="Recent records" description="Your latest worked days">
            <x-slot:actions><a href="{{ route('hours.index') }}" class="dashboard-text-link">View calendar →</a></x-slot:actions>
            @if($recent->isEmpty())<x-dashboard.empty-state compact />@else<div class="recent-records">@foreach($recent as $entry)<a href="{{ route('hours.index', ['month' => substr($entry['work_date'], 0, 7), 'edit' => $entry['id']]) }}" aria-label="Edit hours for {{ $entry['work_date'] }}"><span class="recent-date"><strong>{{ \Carbon\CarbonImmutable::parse($entry['work_date'])->format('d') }}</strong>{{ \Carbon\CarbonImmutable::parse($entry['work_date'])->format('M') }}</span><span><strong>{{ $entry['weekday'] }}</strong>{{ $entry['start_time'] }}–{{ $entry['end_time'] }} · {{ $entry['break_minutes'] }}m break</span><b>{{ $calculator->formatHumanMinutes($entry['net_minutes']) }}</b></a>@endforeach</div>@endif
        </x-dashboard.panel>
    </div>
</x-app-layout>
