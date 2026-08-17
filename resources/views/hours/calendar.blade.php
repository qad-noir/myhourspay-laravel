<x-app-layout>
    <x-slot name="header">Hours Calendar</x-slot>
    @php
        $calculator = app(App\Services\HoursCalculator::class);
        $entriesByDate = collect($summary['entries'])->keyBy('work_date');
        $initialEntry = request()->integer('edit') ? collect($summary['entries'])->firstWhere('id', request()->integer('edit')) : null;
        $openInitially = $errors->any() || request()->boolean('add') || $initialEntry;
        $initialDate = request('date', old('work_date', now(config('hours.timezone'))->toDateString()));
    @endphp
    <div x-data="hoursCalendar(@js($entriesByDate), {{ config('hours.default_break_minutes') }}, @js($initialEntry), @js($initialDate), {{ $openInitially ? 'true' : 'false' }})">
        <x-dashboard.page-header eyebrow="Monday–Sunday workweeks" title="Hours calendar" :description="$monthStart->format('F Y').' · Select any day to add or edit hours.'">
            <x-slot:actions><div class="dashboard-page-actions"><a href="{{ route('hours.reports.index') }}" class="dashboard-button dashboard-button--secondary">View reports</a><button type="button" @click="openEntry('{{ now(config('hours.timezone'))->toDateString() }}')" class="dashboard-button dashboard-button--primary">＋ Add hours</button></div></x-slot:actions>
        </x-dashboard.page-header>
        <section class="dashboard-stats" aria-label="Monthly hours summary">
            <x-dashboard.stat-card label="Month total" :value="$calculator->formatHumanMinutes($monthSummary['total_minutes'])" support="Net hours recorded" tone="analytics" icon="◷" />
            <x-dashboard.stat-card label="Worked days" :value="$monthSummary['worked_days']" support="Days with an entry" icon="▦" />
            <x-dashboard.stat-card label="Daily average" :value="$calculator->formatHumanMinutes($monthSummary['average_minutes'])" support="Across worked days" tone="violet" icon="≈" />
            <x-dashboard.stat-card label="Weekly target" value="40h 00m" support="Monday to Sunday" tone="positive" icon="◎" />
        </section>
        <x-dashboard.panel class="hours-calendar-panel" title="Monthly calendar" description="Worked days show net hours after unpaid breaks.">
            <x-slot:actions><div class="calendar-controls"><a aria-label="Previous month" href="{{ route('hours.index', ['month' => $monthStart->subMonth()->format('Y-m')]) }}">←</a><a href="{{ route('hours.index') }}">Today</a><a aria-label="Next month" href="{{ route('hours.index', ['month' => $monthStart->addMonth()->format('Y-m')]) }}">→</a><strong>{{ $monthStart->format('F Y') }}</strong></div></x-slot:actions>
            <div class="hours-calendar" role="grid" aria-label="{{ $monthStart->format('F Y') }} hours calendar">
                @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day)<div class="hours-calendar__weekday" role="columnheader">{{ $day }}</div>@endforeach
                @for($date = $gridStart; $date <= $gridEnd; $date = $date->addDay())
                    @php($entry = $entriesByDate->get($date->toDateString()))
                    <button type="button" role="gridcell" @click="openEntry('{{ $date->toDateString() }}')" class="hours-day {{ $date->format('Y-m') !== $month ? 'is-adjacent' : '' }} {{ $date->isToday() ? 'is-today' : '' }} {{ $entry ? 'is-worked' : '' }}" aria-label="{{ $entry ? 'Edit' : 'Add' }} hours for {{ $date->format('j F Y') }}"><span>{{ $date->day }}</span>@if($entry)<strong>{{ $calculator->formatHumanMinutes($entry['net_minutes']) }}</strong><small>{{ $entry['start_time'] }}–{{ $entry['end_time'] }}</small>@else<small class="add-day">＋ Add</small>@endif</button>
                @endfor
            </div>
        </x-dashboard.panel>
        <x-dashboard.panel class="hours-records-panel" title="Records this month" :description="$monthSummary['worked_days'].' worked '.str('day')->plural($monthSummary['worked_days'])">
            @if(count($monthSummary['entries']) === 0)<x-dashboard.empty-state><x-slot:action><button type="button" @click="openEntry('{{ $monthStart->toDateString() }}')" class="dashboard-button dashboard-button--primary">Add hours</button></x-slot:action></x-dashboard.empty-state>@else<div class="hours-records"><div class="hours-records__head"><span>Date</span><span>Start</span><span>End</span><span>Break</span><span>Net hours</span><span>Action</span></div>@foreach(collect($monthSummary['entries'])->sortByDesc('work_date') as $entry)<button type="button" @click="openEntry('{{ $entry['work_date'] }}')" class="hours-record"><span data-label="Date"><strong>{{ \Carbon\CarbonImmutable::parse($entry['work_date'])->format('D, j M') }}</strong></span><span data-label="Start">{{ $entry['start_time'] }}</span><span data-label="End">{{ $entry['end_time'] }}</span><span data-label="Break">{{ $entry['break_minutes'] }}m</span><span data-label="Net hours"><strong>{{ $calculator->formatHumanMinutes($entry['net_minutes']) }}</strong></span><span class="hours-record__edit">Edit →</span></button>@endforeach</div>@endif
        </x-dashboard.panel>
        <x-dashboard.hours-form />
    </div>
    @push('modals')<script>function hoursCalendar(entries,defaultBreak,initialEntry,initialDate,openInitially){return{open:openInitially,editing:Boolean(initialEntry),confirmingDelete:false,form:initialEntry?{...initialEntry}:{id:null,work_date:initialDate,start_time:'09:00',end_time:'17:30',break_minutes:defaultBreak,notes:''},init(){if(this.open){document.body.classList.add('dashboard-dialog-open');this.$nextTick(()=>document.getElementById('work_date')?.focus())}},openEntry(date){const entry=entries[date];this.editing=Boolean(entry);this.confirmingDelete=false;this.form=entry?{...entry}:{id:null,work_date:date,start_time:'09:00',end_time:'17:30',break_minutes:defaultBreak,notes:''};this.open=true;document.body.classList.add('dashboard-dialog-open');this.$nextTick(()=>document.getElementById('work_date')?.focus())},close(){this.open=false;this.confirmingDelete=false;document.body.classList.remove('dashboard-dialog-open')},get preview(){const parse=v=>{const p=String(v).split(':').map(Number);return p.length===2?p[0]*60+p[1]:NaN};const minutes=parse(this.form.end_time)-parse(this.form.start_time)-Number(this.form.break_minutes);if(!Number.isFinite(minutes)||minutes<=0)return'Invalid shift';return`${Math.floor(minutes/60)}h ${String(minutes%60).padStart(2,'0')}m`}}}</script>@endpush
</x-app-layout>
