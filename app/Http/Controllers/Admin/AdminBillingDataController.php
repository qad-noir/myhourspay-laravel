<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillingWebhookEvent;
use App\Models\EntitlementGrant;
use App\Models\User;
use App\Services\SubscriptionState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Yajra\DataTables\Facades\DataTables;

class AdminBillingDataController extends Controller
{
    public function subscribers(): JsonResponse
    {
        $query = User::query()->with(['subscriptions.items'])->whereHas('subscriptions');

        return DataTables::eloquent($query)
            ->addColumn('subscriber', fn (User $user) => '<div class="admin-person"><span class="admin-person__avatar">'.e(str($user->name)->substr(0, 1)->upper()).'</span><span><strong>'.e($user->name).'</strong><small>'.e($user->email).'</small></span></div>')
            ->addColumn('plan', fn (User $user) => e(app(SubscriptionState::class)->price($user->subscription('default'))?->plan->name ?? 'Unknown'))
            ->addColumn('status', fn (User $user) => e(app(SubscriptionState::class)->summary($user)['status']))
            ->addColumn('renewal', fn (User $user) => ($user->subscription('default')?->ends_at ?? $user->subscription('default')?->current_period_ends_at)?->format('d M Y') ?? 'Not yet synced')
            ->addColumn('actions', fn (User $user) => view('admin.billing.partials.subscriber-actions', compact('user'))->render())
            ->filterColumn('subscriber', fn ($query, string $keyword) => $query->where(fn ($query) => $query->where('name', 'like', "%{$keyword}%")->orWhere('email', 'like', "%{$keyword}%")))
            ->rawColumns(['subscriber', 'status', 'actions'])
            ->toJson();
    }

    public function grants(Request $request): JsonResponse
    {
        $query = EntitlementGrant::query()->with(['user', 'plan', 'feature', 'creator'])
            ->when($request->input('status') === 'active', fn ($query) => $query->active())
            ->when($request->input('status') === 'revoked', fn ($query) => $query->whereNotNull('revoked_at'))
            ->when($request->input('status') === 'expired', fn ($query) => $query->whereNull('revoked_at')->where('expires_at', '<=', now()));

        return DataTables::eloquent($query)
            ->addColumn('user', fn (EntitlementGrant $grant) => '<div class="admin-person admin-person--compact"><span class="admin-person__avatar">'.e(str($grant->user?->name ?? '?')->substr(0, 1)->upper()).'</span><span><strong>'.e($grant->user?->name ?? 'Deleted user').'</strong><small>'.e($grant->user?->email ?? '').'</small></span></div>')
            ->addColumn('entitlement', fn (EntitlementGrant $grant) => e($grant->plan?->name ?? $grant->feature?->name ?? 'Unknown'))
            ->addColumn('period', fn (EntitlementGrant $grant) => e(($grant->starts_at?->format('d M Y') ?? 'Now').' → '.($grant->expires_at?->format('d M Y') ?? 'Permanent')))
            ->addColumn('status', function (EntitlementGrant $grant): string {
                $status = $grant->revoked_at ? 'Revoked' : ($grant->expires_at?->isPast() ? 'Expired' : ($grant->starts_at?->isFuture() ? 'Scheduled' : 'Active'));

                return '<span class="admin-status admin-status--'.strtolower($status).'"><i></i>'.$status.'</span>';
            })
            ->addColumn('reason', fn (EntitlementGrant $grant) => e($grant->reason))
            ->addColumn('actions', fn (EntitlementGrant $grant) => view('admin.billing.partials.grant-actions', compact('grant'))->render())
            ->filterColumn('user', fn ($query, string $keyword) => $query->whereHas('user', fn ($query) => $query->where('name', 'like', "%{$keyword}%")->orWhere('email', 'like', "%{$keyword}%")))
            ->rawColumns(['user', 'status', 'actions'])
            ->toJson();
    }

    public function webhooks(Request $request): JsonResponse
    {
        $request->merge(['length' => min(100, max(1, (int) $request->input('length', 10)))]);

        $columns = ['id', 'stripe_event_id', 'type', 'status', 'created_at', 'processed_at', 'error_message'];
        foreach (['attempts', 'lease_until'] as $column) {
            if (Schema::hasColumn('billing_webhook_events', $column)) {
                $columns[] = $column;
            }
        }

        return DataTables::eloquent(BillingWebhookEvent::query()->select($columns))
            ->editColumn('type', fn (BillingWebhookEvent $event) => e($event->type))
            ->editColumn('status', fn (BillingWebhookEvent $event) => '<span class="admin-status admin-status--'.e($event->status).'"><i></i>'.e(str($event->status)->headline()).'</span>')
            ->addColumn('attempts', fn (BillingWebhookEvent $event) => $event->attempts ?? '—')
            ->addColumn('received', fn (BillingWebhookEvent $event) => $event->created_at->format('d M Y H:i:s'))
            ->addColumn('processed', fn (BillingWebhookEvent $event) => $event->processed_at?->format('d M Y H:i:s') ?? '—')
            ->addColumn('message', fn (BillingWebhookEvent $event) => e($event->error_message ? str($event->error_message)->limit(100) : '—'))
            ->orderColumn('received', 'created_at $1')
            ->orderColumn('processed', 'processed_at $1')
            ->addColumn('actions', fn (BillingWebhookEvent $event) => view('admin.billing.partials.webhook-actions', compact('event'))->render())
            ->rawColumns(['status', 'actions'])
            ->toJson();
    }
}
