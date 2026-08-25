<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillingWebhookEvent;
use App\Models\EntitlementGrant;
use App\Models\PlanPrice;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class AdminBillingDataController extends Controller
{
    public function subscribers(): JsonResponse
    {
        $pricePlans = PlanPrice::query()->with('plan')->whereNotNull('stripe_price_id')->get()->keyBy('stripe_price_id');
        $query = User::query()->with(['subscriptions.items'])->whereHas('subscriptions');

        return DataTables::eloquent($query)
            ->addColumn('subscriber', fn (User $user) => '<div class="admin-person"><span class="admin-person__avatar">'.e(str($user->name)->substr(0, 1)->upper()).'</span><span><strong>'.e($user->name).'</strong><small>'.e($user->email).'</small></span></div>')
            ->addColumn('plan', function (User $user) use ($pricePlans): string {
                $priceId = $user->subscriptions->first()?->items->first()?->stripe_price;

                return e($pricePlans->get($priceId)?->plan?->name ?? 'Unknown');
            })
            ->addColumn('status', function (User $user): string {
                $status = $user->subscriptions->first()?->stripe_status ?? 'none';

                return '<span class="admin-status admin-status--'.e(strtolower($status)).'"><i></i>'.e(str($status)->replace('_', ' ')->headline()).'</span>';
            })
            ->addColumn('renewal', fn (User $user) => $user->subscriptions->first()?->ends_at?->format('d M Y') ?? 'Recurring')
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

    public function webhooks(): JsonResponse
    {
        return DataTables::eloquent(BillingWebhookEvent::query())
            ->editColumn('type', fn (BillingWebhookEvent $event) => e($event->type))
            ->editColumn('status', fn (BillingWebhookEvent $event) => '<span class="admin-status admin-status--'.e($event->status).'"><i></i>'.e(str($event->status)->headline()).'</span>')
            ->addColumn('received', fn (BillingWebhookEvent $event) => $event->created_at->format('d M Y H:i:s'))
            ->addColumn('processed', fn (BillingWebhookEvent $event) => $event->processed_at?->format('d M Y H:i:s') ?? '—')
            ->addColumn('message', fn (BillingWebhookEvent $event) => e($event->error_message ? str($event->error_message)->limit(100) : '—'))
            ->orderColumn('received', 'created_at $1')
            ->orderColumn('processed', 'processed_at $1')
            ->rawColumns(['status'])
            ->toJson();
    }
}
