<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillingPaymentReview;
use App\Models\BillingWebhookEvent;
use App\Services\AdminAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Cashier;
use Yajra\DataTables\Facades\DataTables;

class AdminPaymentReviewController extends Controller
{
    public function index()
    {
        return view('admin.billing.payment-reviews');
    }

    public function data(Request $request)
    {
        $request->merge(['length' => min(100, max(1, (int) $request->input('length', 10)))]);

        return DataTables::eloquent(BillingPaymentReview::with('user'))
            ->addColumn('customer', fn ($row) => e($row->user?->email ?? $row->stripe_customer_id ?? 'Unlinked customer'))
            ->addColumn('total', fn ($row) => e(Cashier::formatAmount($row->amount, $row->currency)))
            ->addColumn('links', fn ($row) => '<a target="_blank" rel="noopener noreferrer" href="https://dashboard.stripe.com/'.(str_starts_with(config('cashier.secret', ''), 'sk_test_') ? 'test/' : '').'payments/'.rawurlencode($row->charge_id ?? '').'">Stripe payment</a>')
            ->rawColumns(['links'])->toJson();
    }

    public function retry(Request $request, BillingWebhookEvent $event, AdminAudit $audit)
    {
        DB::transaction(function () use ($request, $event, $audit) {
            $event = BillingWebhookEvent::lockForUpdate()->findOrFail($event->id);
            abort_unless(in_array($event->status, ['failed', 'exhausted', 'processing']) && ! $event->lease_until?->isFuture() && $event->payload, 409, 'This event cannot be retried now.');
            $before = ['status' => $event->status, 'attempts' => $event->attempts];
            $event->update(['status' => 'received', 'attempts' => 0, 'available_at' => now(), 'dispatched_at' => null, 'lease_until' => null, 'lease_token' => null, 'error_message' => null]);
            $audit->record($request, 'billing.webhook.retry', $event, $before, ['status' => 'received']);
        });

        return back()->with('status', 'Event queued for another processing attempt.');
    }
}
