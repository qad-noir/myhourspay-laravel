<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientInvoice;
use App\Models\CompensationRate;
use App\Models\ExpectedSchedule;
use App\Models\NotificationPreference;
use App\Models\Project;
use App\Models\ReportTemplate;
use App\Models\ScheduledReport;
use App\Services\CurrentWorkspace;
use App\Services\FeatureAccess;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ProController extends Controller
{
    public function __construct(
        private readonly CurrentWorkspace $current,
        private readonly FeatureAccess $features,
    ) {}

    public function storeClient(Request $request): RedirectResponse
    {
        $workspace = $this->current->for($request->user());
        $data = $request->validate(['name' => ['required', 'string', 'min:2', 'max:100', Rule::unique('clients')->where('workspace_id', $workspace->id)], 'email' => ['nullable', 'email', 'max:190'], 'address' => ['nullable', 'string', 'max:2000']]);
        $workspace->clients()->create([...$data, 'currency' => $workspace->currency]);

        return back()->with('status', 'Client created.');
    }

    public function updateClient(Request $request, Client $client): RedirectResponse
    {
        $this->guardWorkspace($request, $client->workspace_id);
        $data = $request->validate(['name' => ['required', 'string', 'min:2', 'max:100', Rule::unique('clients')->where('workspace_id', $client->workspace_id)->ignore($client)], 'email' => ['nullable', 'email', 'max:190'], 'address' => ['nullable', 'string', 'max:2000'], 'active' => ['nullable', 'boolean']]);
        $client->update([...$data, 'active' => $request->boolean('active')]);

        return back()->with('status', 'Client updated.');
    }

    public function deleteClient(Request $request, Client $client): RedirectResponse
    {
        $this->guardWorkspace($request, $client->workspace_id);
        if ($client->invoices()->exists()) {
            return back()->withErrors(['client' => 'Clients with invoices are retained for financial history.']);
        }
        $client->delete();

        return back()->with('status', 'Client archived.');
    }

    public function storeProject(Request $request): RedirectResponse
    {
        $workspace = $this->current->for($request->user());
        $data = $request->validate(['client_id' => ['nullable', Rule::exists('clients', 'id')->where('workspace_id', $workspace->id)], 'name' => ['required', 'string', 'min:2', 'max:100'], 'code' => ['nullable', 'string', 'max:50', Rule::unique('projects')->where('workspace_id', $workspace->id)], 'hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:100000']]);
        $workspace->projects()->create([...$data, 'hourly_rate_minor' => filled($data['hourly_rate'] ?? null) ? (int) round($data['hourly_rate'] * 100) : null, 'currency' => $workspace->currency]);

        return back()->with('status', 'Project created.');
    }

    public function deleteProject(Request $request, Project $project): RedirectResponse
    {
        $this->guardWorkspace($request, $project->workspace_id);
        $project->delete();

        return back()->with('status', 'Project archived. Existing hours keep their historical assignment.');
    }

    public function storeRate(Request $request): RedirectResponse
    {
        $workspace = $this->current->for($request->user());
        $data = $request->validate(['effective_from' => ['required', 'date'], 'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'], 'hourly_rate' => ['required', 'numeric', 'min:0', 'max:100000'], 'overtime_multiplier' => ['required', 'numeric', 'min:1', 'max:5']]);
        CompensationRate::query()->updateOrCreate(['workspace_id' => $workspace->id, 'user_id' => $request->user()->id, 'effective_from' => $data['effective_from']], ['effective_to' => $data['effective_to'] ?? null, 'hourly_rate_minor' => (int) round($data['hourly_rate'] * 100), 'overtime_multiplier_bps' => (int) round($data['overtime_multiplier'] * 10000), 'currency' => $workspace->currency]);

        return back()->with('status', 'Effective pay rate saved. Existing entries retain their snapshotted rate.');
    }

    public function storeExpectedSchedule(Request $request): RedirectResponse
    {
        $workspace = $this->current->for($request->user());
        $data = $request->validate(['project_id' => ['nullable', Rule::exists('projects', 'id')->where('workspace_id', $workspace->id)], 'day_of_week' => ['required', 'integer', 'between:1,7'], 'start_time' => ['required', 'date_format:H:i'], 'end_time' => ['required', 'date_format:H:i', 'after:start_time'], 'break_minutes' => ['required', 'integer', 'min:0', 'max:1439'], 'break_type' => ['required', Rule::in(['paid', 'unpaid'])], 'starts_on' => ['nullable', 'date'], 'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on']]);
        $workspace->expectedSchedules()->create([...$data, 'user_id' => $request->user()->id]);

        return back()->with('status', 'Weekly schedule suggestion saved. It will never create worked hours automatically.');
    }

    public function convertSchedule(Request $request, ExpectedSchedule $schedule): RedirectResponse
    {
        $this->guardWorkspace($request, $schedule->workspace_id);
        $data = $request->validate(['work_date' => ['required', 'date']]);
        $date = CarbonImmutable::parse($data['work_date']);
        if ($date->isoWeekday() !== $schedule->day_of_week) {
            return back()->withErrors(['work_date' => 'Choose a date matching this scheduled weekday.']);
        }
        try {
            $request->user()->hoursEntries()->create(['workspace_id' => $schedule->workspace_id, 'project_id' => $schedule->project_id, 'work_date' => $date->toDateString(), 'start_time' => substr($schedule->start_time, 0, 5), 'end_time' => substr($schedule->end_time, 0, 5), 'break_minutes' => $schedule->break_minutes, 'break_type' => $schedule->break_type, 'notes' => 'Created from a schedule suggestion']);
        } catch (UniqueConstraintViolationException) {
            return back()->withErrors(['work_date' => 'Hours already exist for this date.']);
        }

        return redirect()->route('hours.index', ['month' => $date->format('Y-m'), 'edit' => $request->user()->hoursEntries()->whereDate('work_date', $date)->value('id')])->with('status', 'Suggestion converted to a worked-hours entry for review.');
    }

    public function updateReminders(Request $request): RedirectResponse
    {
        $workspace = $this->current->for($request->user());
        $data = $request->validate(['types' => ['nullable', 'array'], 'types.*' => [Rule::in(['missing_entry', 'weekly_target', 'overtime', 'trial_ending', 'payment_failed'])], 'email' => ['nullable', 'boolean'], 'in_app' => ['nullable', 'boolean']]);
        foreach (['missing_entry', 'weekly_target', 'overtime', 'trial_ending', 'payment_failed'] as $type) {
            NotificationPreference::query()->updateOrCreate(['workspace_id' => $workspace->id, 'user_id' => $request->user()->id, 'type' => $type], ['enabled' => in_array($type, $data['types'] ?? [], true), 'channels' => array_keys(array_filter(['mail' => $request->boolean('email'), 'database' => $request->boolean('in_app')]))]);
        }

        return back()->with('status', 'Reminder preferences updated.');
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        $workspace = $this->current->for($request->user());
        $data = $request->validate(['name' => ['required', 'string', 'min:3', 'max:100', Rule::unique('report_templates')->where('workspace_id', $workspace->id)->where('user_id', $request->user()->id)], 'format' => ['required', Rule::in(['xlsx', 'pdf', 'csv'])], 'columns' => ['nullable', 'array']]);
        ReportTemplate::query()->create(['workspace_id' => $workspace->id, 'user_id' => $request->user()->id, 'name' => $data['name'], 'format' => $data['format'], 'filters' => [], 'columns' => $data['columns'] ?? ['date', 'hours', 'overtime', 'break', 'earnings']]);

        return back()->with('status', 'Report template saved.');
    }

    public function scheduleReport(Request $request, ReportTemplate $template): RedirectResponse
    {
        $this->guardWorkspace($request, $template->workspace_id);
        $limit = $this->features->value($request->user(), 'scheduled_reports', $this->current->for($request->user()));
        if ($limit !== null && ScheduledReport::query()->where('user_id', $request->user()->id)->where('active', true)->count() >= (int) $limit) {
            return back()->withErrors(['schedule' => 'Your active scheduled-report quota has been reached.']);
        }
        $data = $request->validate(['frequency' => ['required', Rule::in(['weekly', 'monthly'])], 'recipients' => ['required', 'string', 'max:1000']]);
        $recipients = collect(explode(',', $data['recipients']))->map(fn ($email) => trim($email))->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))->unique()->values();
        if ($recipients->isEmpty()) {
            return back()->withErrors(['recipients' => 'Enter at least one valid email address.']);
        }
        $template->schedules()->create(['workspace_id' => $template->workspace_id, 'user_id' => $request->user()->id, 'frequency' => $data['frequency'], 'recipients' => $recipients->all(), 'next_run_at' => $data['frequency'] === 'weekly' ? now()->addWeek()->startOfDay() : now()->addMonth()->startOfMonth()]);

        return back()->with('status', 'Scheduled report created.');
    }

    public function createInvoice(Request $request): RedirectResponse
    {
        $workspace = $this->current->for($request->user());
        $data = $request->validate(['client_id' => ['required', Rule::exists('clients', 'id')->where('workspace_id', $workspace->id)], 'start' => ['required', 'date'], 'end' => ['required', 'date', 'after_or_equal:start'], 'due_on' => ['required', 'date', 'after_or_equal:today'], 'tax_percent' => ['required', 'numeric', 'min:0', 'max:100']]);
        $entries = $request->user()->hoursEntries()->with('project')->where('workspace_id', $workspace->id)->where('billable', true)->whereBetween('work_date', [$data['start'], $data['end']])->whereHas('project', fn ($query) => $query->where('client_id', $data['client_id']))->whereDoesntHave('invoiceLine')->orderBy('work_date')->get();
        if ($entries->isEmpty()) {
            return back()->withErrors(['invoice' => 'No uninvoiced billable entries match this client and period.']);
        }
        if ($entries->contains(fn ($entry) => $entry->hourly_rate_minor === null)) {
            return back()->withErrors(['invoice' => 'Every selected entry needs a snapshotted hourly rate before invoicing.']);
        }

        $invoice = DB::transaction(function () use ($workspace, $data, $entries): ClientInvoice {
            $sequence = ClientInvoice::withTrashed()->where('workspace_id', $workspace->id)->lockForUpdate()->count() + 1;
            $invoice = $workspace->invoices()->create(['client_id' => $data['client_id'], 'number' => 'MHP-'.now()->format('Y').'-'.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT), 'currency' => $workspace->currency, 'due_on' => $data['due_on'], 'snapshot' => ['workspace_name' => $workspace->name, 'period' => [$data['start'], $data['end']]]]);
            foreach ($entries as $entry) {
                $amount = (int) round($entry->net_minutes / 60 * $entry->hourly_rate_minor);
                $invoice->lines()->create(['hours_entry_id' => $entry->id, 'description' => ($entry->project?->name ?? 'Professional services').' · '.$entry->work_date->format('d M Y'), 'quantity_minutes' => $entry->net_minutes, 'rate_minor' => $entry->hourly_rate_minor, 'amount_minor' => $amount, 'snapshot' => ['project' => $entry->project?->name, 'date' => $entry->work_date->toDateString(), 'start' => $entry->start_time, 'end' => $entry->end_time]]);
            }
            $subtotal = $invoice->lines()->sum('amount_minor');
            $tax = (int) round($subtotal * ((float) $data['tax_percent'] / 100));
            $invoice->update(['subtotal_minor' => $subtotal, 'tax_minor' => $tax, 'total_minor' => $subtotal + $tax]);

            return $invoice;
        });

        return redirect()->route('pro.invoices.show', $invoice)->with('status', 'Draft invoice created from snapshotted time and rates.');
    }

    public function showInvoice(Request $request, ClientInvoice $invoice): View
    {
        $this->guardWorkspace($request, $invoice->workspace_id);
        $invoice->load(['client', 'lines', 'workspace.branding']);

        return view('pro.invoice', compact('invoice'));
    }

    public function invoicePdf(Request $request, ClientInvoice $invoice): Response
    {
        $this->guardWorkspace($request, $invoice->workspace_id);
        $invoice->load(['client', 'lines', 'workspace.branding']);
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('pro.invoice-pdf', compact('invoice'))->render());
        $dompdf->setPaper('A4');
        $dompdf->render();

        return response($dompdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$invoice->number.'.pdf"']);
    }

    public function updateInvoiceStatus(Request $request, ClientInvoice $invoice): RedirectResponse
    {
        $this->guardWorkspace($request, $invoice->workspace_id);
        $data = $request->validate(['status' => ['required', Rule::in(['issued', 'paid', 'void'])]]);
        $updates = ['status' => $data['status']];
        if ($data['status'] === 'issued') {
            $updates['issued_on'] = today();
            $updates['sent_at'] = now();
        } if ($data['status'] === 'paid') {
            $updates['paid_at'] = now();
        } if ($data['status'] === 'void') {
            $updates['voided_at'] = now();
        } $invoice->update($updates);

        return back()->with('status', 'Invoice status updated.');
    }

    private function guardWorkspace(Request $request, int $workspaceId): void
    {
        abort_unless($this->current->for($request->user())->id === $workspaceId, 404);
    }
}
