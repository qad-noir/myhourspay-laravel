<?php

namespace App\Http\Controllers;

use App\Models\CalendarConnection;
use App\Models\CalendarEvent;
use App\Services\CalendarIntegrationService;
use App\Services\CurrentWorkspace;
use App\Services\FeatureAccess;
use App\Services\OperationalIncidentRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class CalendarIntegrationController extends Controller
{
    public function __construct(
        private readonly CurrentWorkspace $current,
        private readonly CalendarIntegrationService $calendars,
        private readonly OperationalIncidentRecorder $incidents,
    ) {}

    public function redirect(Request $request, string $provider, FeatureAccess $features): RedirectResponse
    {
        $workspace = $this->current->for($request->user());
        $limit = $features->value($request->user(), 'calendar_integrations', $workspace);
        $connections = CalendarConnection::query()->where('workspace_id', $workspace->id)->where('user_id', $request->user()->id)->count();
        if ($limit !== null && $connections >= (int) $limit) {
            return back()->withErrors(['calendar' => 'Your calendar connection limit has been reached.']);
        }

        try {
            $state = Str::random(48);
            $request->session()->put('calendar_oauth', ['state' => $state, 'provider' => $provider, 'workspace_id' => $workspace->id]);

            return redirect()->away($this->calendars->authorizationUrl($provider, $state));
        } catch (Throwable $exception) {
            return $this->failure($request, $exception, 'calendar.oauth_start_failed');
        }
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $oauth = (array) $request->session()->pull('calendar_oauth', []);
        if (! hash_equals((string) ($oauth['state'] ?? ''), (string) $request->query('state')) || ($oauth['provider'] ?? null) !== $provider) {
            return redirect(route('pro.index').'#integrations')->withErrors(['calendar' => 'The calendar connection expired or could not be verified. Please try again.']);
        }
        $workspace = $this->current->for($request->user());
        abort_unless((int) ($oauth['workspace_id'] ?? 0) === $workspace->id, 403);

        try {
            if ($request->filled('error')) {
                throw new \RuntimeException('Calendar authorization was declined: '.$request->query('error'));
            }
            $token = $this->calendars->exchange($provider, (string) $request->query('code'));
            $account = $this->calendars->account($provider, $token['access_token']);
            CalendarConnection::query()->updateOrCreate(
                ['workspace_id' => $workspace->id, 'user_id' => $request->user()->id, 'provider' => $provider],
                ['external_account_id' => $account['id'] ?: $account['email'], 'access_token' => $token['access_token'], 'refresh_token' => $token['refresh_token'], 'token_expires_at' => $token['expires_at'], 'status' => 'active'],
            );

            return redirect(route('pro.index').'#integrations')->with('status', ucfirst($provider).' Calendar connected. Review imported events before logging them.');
        } catch (Throwable $exception) {
            return $this->failure($request, $exception, 'calendar.oauth_callback_failed');
        }
    }

    public function sync(Request $request, CalendarConnection $connection): RedirectResponse
    {
        $this->guard($request, $connection);
        try {
            $count = $this->calendars->sync($connection, CarbonImmutable::now()->startOfMonth()->subMonth(), CarbonImmutable::now()->endOfMonth()->addMonth());

            return back()->with('status', $count.' calendar '.Str::plural('event', $count).' refreshed as reviewable suggestions.');
        } catch (Throwable $exception) {
            $connection->update(['status' => 'error']);

            return $this->failure($request, $exception, 'calendar.sync_failed');
        }
    }

    public function convert(Request $request, CalendarEvent $event): RedirectResponse
    {
        $this->guardEvent($request, $event);
        $request->validate(['break_minutes' => ['nullable', 'integer', 'min:0', 'max:1439'], 'break_type' => ['nullable', 'in:paid,unpaid']]);
        if ($event->status !== 'suggested') {
            return back()->withErrors(['calendar' => 'This event has already been reviewed.']);
        }
        try {
            $entry = $request->user()->hoursEntries()->create([
                'workspace_id' => $event->workspace_id,
                'work_date' => $event->starts_at->timezone(config('hours.timezone'))->toDateString(),
                'start_time' => $event->starts_at->timezone(config('hours.timezone'))->format('H:i'),
                'end_time' => $event->ends_at->timezone(config('hours.timezone'))->format('H:i'),
                'break_minutes' => $request->integer('break_minutes', 0),
                'break_type' => $request->input('break_type', 'unpaid'),
                'notes' => 'Imported from '.$event->connection->provider.' Calendar: '.$event->summary,
            ]);
            $event->update(['status' => 'converted', 'hours_entry_id' => $entry->id]);
        } catch (UniqueConstraintViolationException) {
            return back()->withErrors(['calendar' => 'Hours already exist for this date. The calendar event was left unchanged.']);
        } catch (Throwable $exception) {
            return $this->failure($request, $exception, 'calendar.event_conversion_failed');
        }

        return redirect()->route('hours.index', ['month' => $event->starts_at->format('Y-m')])->with('status', 'Calendar suggestion converted to worked hours.');
    }

    public function ignore(Request $request, CalendarEvent $event): RedirectResponse
    {
        $this->guardEvent($request, $event);
        $event->update(['status' => 'ignored']);

        return back()->with('status', 'Calendar suggestion ignored.');
    }

    public function disconnect(Request $request, CalendarConnection $connection): RedirectResponse
    {
        $this->guard($request, $connection);
        $connection->delete();

        return back()->with('status', 'Calendar disconnected. Existing confirmed hours were retained.');
    }

    private function guard(Request $request, CalendarConnection $connection): void
    {
        abort_unless($connection->user_id === $request->user()->id && $connection->workspace_id === $this->current->for($request->user())->id, 404);
    }

    private function guardEvent(Request $request, CalendarEvent $event): void
    {
        abort_unless($event->user_id === $request->user()->id && $event->workspace_id === $this->current->for($request->user())->id, 404);
    }

    private function failure(Request $request, Throwable $exception, string $event): RedirectResponse
    {
        Log::error('Calendar integration operation failed.', ['event' => $event, 'user_id' => $request->user()?->id, 'workspace_id' => $request->user()?->current_workspace_id, 'exception' => $exception]);
        $incident = $this->incidents->record($event, $exception, ['name' => $request->user()?->name, 'email' => $request->user()?->email]);

        return redirect(route('pro.index').'#integrations')->withErrors(['calendar' => 'Calendar could not be updated right now. Please try again or contact '.config('site.contact.email').' with reference '.$incident->reference.'.']);
    }
}
