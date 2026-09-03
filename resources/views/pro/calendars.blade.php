<x-app-layout>
<x-slot name="header">Pro tools · Calendars</x-slot>
<x-dashboard.page-header eyebrow="Calendar integrations" title="Review events before logging time" description="Connected events stay as suggestions. Nothing becomes worked time until you explicitly approve it." />
<x-tools.navigation area="pro" :$access />

<section class="dashboard-panel tool-module-panel">
    <div class="dashboard-panel-heading"><div><p class="dashboard-eyebrow">Connected calendars</p><h2>Calendar sources</h2></div><span>{{ $connections->count() }} connected</span></div>
    @if(!$access['calendar_integrations'])
        <x-pro.locked feature="calendar integrations" />
    @else
        <div class="calendar-provider-grid">
            @foreach(['google'=>'Google Calendar','microsoft'=>'Microsoft Outlook'] as $provider=>$label)
                @php($connection=$connections->firstWhere('provider',$provider))
                <article>
                    <span class="tool-provider-icon"><x-dashboard.icon name="calendar" :size="22" /></span>
                    <strong>{{ $label }}</strong>
                    <p>Imported events remain reviewable suggestions until you convert them.</p>
                    @if($connection)
                        <span>{{ $connection->events_count }} waiting · last synced {{ $connection->last_synced_at?->diffForHumans() ?? 'never' }}</span>
                        <div class="calendar-provider-actions"><form method="POST" action="{{ route('pro.calendars.sync',$connection) }}">@csrf<button class="dashboard-button dashboard-button--secondary">Refresh events</button></form><form method="POST" action="{{ route('pro.calendars.disconnect',$connection) }}" data-confirm="Disconnect this calendar? Confirmed hours will be retained.">@csrf @method('DELETE')<button class="dashboard-button dashboard-button--danger">Disconnect</button></form></div>
                    @elseif($calendarProvidersConfigured->get($provider))
                        <a class="dashboard-button dashboard-button--primary" href="{{ route('pro.calendars.redirect',$provider) }}">Connect {{ $label }}</a>
                    @else
                        <div class="calendar-provider-unavailable"><span>Not configured</span><small>This integration will appear when the platform administrator enables it.</small><button class="dashboard-button dashboard-button--secondary" type="button" disabled>Unavailable</button></div>
                    @endif
                </article>
            @endforeach
        </div>

        @foreach($connections as $connection)
            @if($connection->events->isNotEmpty())
                <div class="calendar-suggestion-list"><header><strong>{{ str($connection->provider)->headline() }} suggestions</strong><span>Review before adding to worked hours</span></header>
                    @foreach($connection->events as $event)
                        <article><div><strong>{{ $event->summary }}</strong><small>{{ $event->starts_at->timezone(config('hours.timezone'))->format('D, d M Y · H:i') }}–{{ $event->ends_at->timezone(config('hours.timezone'))->format('H:i') }}</small></div><div><form method="POST" action="{{ route('pro.calendars.events.convert',$event) }}">@csrf<input type="hidden" name="break_minutes" value="0"><input type="hidden" name="break_type" value="unpaid"><button class="dashboard-button dashboard-button--primary">Add as hours</button></form><form method="POST" action="{{ route('pro.calendars.events.ignore',$event) }}" data-confirm="Ignore this calendar suggestion? It will no longer appear for review." data-confirm-button="Ignore suggestion">@csrf<button class="dashboard-button dashboard-button--secondary">Ignore</button></form></div></article>
                    @endforeach
                </div>
            @endif
        @endforeach

        @if($connections->isEmpty())
            <x-tools.empty-state icon="calendar" title="No calendar is connected" description="Connect an available provider above. Imported events will wait here for review." />
        @endif
    @endif
</section>
</x-app-layout>
