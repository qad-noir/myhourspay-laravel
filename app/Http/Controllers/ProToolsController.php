<?php

namespace App\Http\Controllers;

use App\Models\CalendarConnection;
use App\Models\HoursEntry;
use App\Models\NotificationPreference;
use App\Models\ReportTemplate;
use App\Services\CalendarIntegrationService;
use App\Services\CurrentWorkspace;
use App\Services\FeatureAccess;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class ProToolsController extends Controller
{
    private const FEATURES = [
        'clients_projects',
        'earnings',
        'recurring_schedules',
        'smart_reminders',
        'export_templates',
        'scheduled_reports',
        'calendar_integrations',
        'invoicing',
    ];

    public function __construct(
        private readonly CurrentWorkspace $current,
        private readonly FeatureAccess $features,
        private readonly CalendarIntegrationService $calendars,
    ) {}

    public function overview(Request $request): View
    {
        $context = $this->context($request);
        $workspace = $context['workspace'];
        $access = $context['access'];
        $user = $request->user();

        $clientCount = $access['clients_projects'] ? $workspace->clients()->count() : 0;
        $projectCount = $access['clients_projects'] ? $workspace->projects()->count() : 0;
        $rateCount = $access['earnings'] ? $workspace->compensationRates()->where('user_id', $user->id)->count() : 0;
        $scheduleCount = $access['recurring_schedules'] ? $workspace->expectedSchedules()->where('user_id', $user->id)->count() : 0;
        $templateCount = $access['export_templates'] ? ReportTemplate::query()->where('workspace_id', $workspace->id)->where('user_id', $user->id)->count() : 0;
        $connectionCount = $access['calendar_integrations'] ? CalendarConnection::query()->where('workspace_id', $workspace->id)->where('user_id', $user->id)->count() : 0;
        $invoiceCount = $access['invoicing'] ? $workspace->invoices()->count() : 0;

        $clientProjectExists = $access['clients_projects'] && $workspace->projects()->whereNotNull('client_id')->exists();
        $rateReady = $access['earnings'] && ($rateCount > 0 || $workspace->projects()->whereNotNull('hourly_rate_minor')->exists());
        $invoiceHoursReady = $access['invoicing'] && $this->invoiceReadyEntries($request)->exists();

        return view('pro.overview', $context + [
            'moduleCounts' => [
                'clients' => $clientCount.' clients · '.$projectCount.' projects',
                'earnings' => $rateCount.' '.str('rate')->plural($rateCount),
                'schedules' => $scheduleCount.' '.str('schedule')->plural($scheduleCount),
                'reminders' => $access['smart_reminders'] ? 'Preferences ready' : 'Premium feature',
                'reports' => $templateCount.' '.str('template')->plural($templateCount),
                'calendars' => $connectionCount.' connected',
                'invoices' => $invoiceCount.' '.str('invoice')->plural($invoiceCount),
            ],
            'workflowReady' => [
                'client' => $clientCount > 0,
                'project' => $clientProjectExists,
                'rate' => $rateReady,
                'hours' => $invoiceHoursReady,
            ],
        ]);
    }

    public function clients(Request $request): View
    {
        $context = $this->context($request);
        $workspace = $context['workspace'];
        $enabled = $context['access']['clients_projects'];

        return view('pro.clients', $context + [
            'clients' => $enabled ? $workspace->clients()->withCount('projects')->orderBy('name')->get() : collect(),
            'projects' => $enabled ? $workspace->projects()->with('client')->orderBy('name')->get() : collect(),
        ]);
    }

    public function earnings(Request $request): View
    {
        $context = $this->context($request);

        return view('pro.earnings', $context + [
            'rates' => $context['access']['earnings']
                ? $context['workspace']->compensationRates()->where('user_id', $request->user()->id)->latest('effective_from')->get()
                : collect(),
        ]);
    }

    public function schedules(Request $request): View
    {
        $context = $this->context($request);
        $enabled = $context['access']['recurring_schedules'];

        return view('pro.schedules', $context + [
            'schedules' => $enabled
                ? $context['workspace']->expectedSchedules()->with('project')->where('user_id', $request->user()->id)->orderBy('day_of_week')->get()
                : collect(),
            'projects' => $enabled ? $context['workspace']->projects()->orderBy('name')->get() : collect(),
        ]);
    }

    public function reminders(Request $request): View
    {
        $context = $this->context($request);

        return view('pro.reminders', $context + [
            'preferences' => $context['access']['smart_reminders']
                ? NotificationPreference::query()->where('workspace_id', $context['workspace']->id)->where('user_id', $request->user()->id)->get()->keyBy('type')
                : collect(),
        ]);
    }

    public function reports(Request $request): View
    {
        $context = $this->context($request);

        return view('pro.reports', $context + [
            'templates' => $context['access']['export_templates']
                ? ReportTemplate::query()->where('workspace_id', $context['workspace']->id)->where('user_id', $request->user()->id)->with('schedules')->get()
                : collect(),
        ]);
    }

    public function calendars(Request $request): View
    {
        $context = $this->context($request);
        $enabled = $context['access']['calendar_integrations'];

        return view('pro.calendars', $context + [
            'connections' => $enabled
                ? CalendarConnection::query()->where('workspace_id', $context['workspace']->id)->where('user_id', $request->user()->id)
                    ->with(['events' => fn ($query) => $query->where('status', 'suggested')->orderBy('starts_at')->limit(25)])
                    ->withCount(['events' => fn ($query) => $query->where('status', 'suggested')])->get()
                : collect(),
            'calendarProvidersConfigured' => collect(['google', 'microsoft'])
                ->mapWithKeys(fn (string $provider) => [$provider => $this->calendars->configured($provider)]),
        ]);
    }

    public function invoices(Request $request): View
    {
        $context = $this->context($request);
        $workspace = $context['workspace'];
        $enabled = $context['access']['invoicing'];
        $readyEntries = $this->invoiceReadyEntries($request);
        $invoiceClients = $enabled
            ? $workspace->clients()->where('active', true)->whereHas('projects.hoursEntries', fn ($query) => $query
                ->where('hours_entries.user_id', $request->user()->id)
                ->where('hours_entries.workspace_id', $workspace->id)
                ->where('billable', true)
                ->whereNotNull('hourly_rate_minor')
                ->whereDoesntHave('invoiceLine'))->orderBy('name')->get()
            : collect();

        return view('pro.invoices', $context + [
            'clients' => $enabled ? $workspace->clients()->where('active', true)->orderBy('name')->get() : collect(),
            'invoiceClients' => $invoiceClients,
            'invoices' => $enabled ? $workspace->invoices()->with('client')->latest()->limit(20)->get() : collect(),
            'invoiceReadiness' => [
                'client' => $enabled && $workspace->clients()->where('active', true)->exists(),
                'project' => $enabled && $workspace->projects()->whereNotNull('client_id')->where('active', true)->exists(),
                'rate' => $enabled && ($workspace->compensationRates()->where('user_id', $request->user()->id)->exists() || $workspace->projects()->whereNotNull('hourly_rate_minor')->exists()),
                'hours' => $enabled && $readyEntries->exists(),
            ],
        ]);
    }

    private function context(Request $request): array
    {
        $workspace = $this->current->for($request->user());
        $access = collect(self::FEATURES)
            ->mapWithKeys(fn (string $feature) => [$feature => $this->features->allows($request->user(), $feature, $workspace)]);

        return compact('workspace', 'access');
    }

    private function invoiceReadyEntries(Request $request): Builder
    {
        $workspace = $this->current->for($request->user());

        return HoursEntry::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $request->user()->id)
            ->where('billable', true)
            ->whereNotNull('hourly_rate_minor')
            ->whereDoesntHave('invoiceLine')
            ->whereHas('project', fn ($query) => $query->whereNotNull('client_id'));
    }
}
