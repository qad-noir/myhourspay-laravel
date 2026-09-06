<?php

namespace App\Http\Controllers;

use App\Models\Feature;
use App\Models\Plan;
use App\Services\BillingSettings;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PricingController extends Controller
{
    public function __invoke(Request $request, BillingSettings $settings): View
    {
        $interval = $request->query('interval') === 'yearly' ? 'yearly' : 'monthly';
        $plans = Plan::where('active', true)->with([
            'prices' => fn ($q) => $q->where('active', true)->orderByDesc('id'),
            'features' => fn ($q) => $q->where('mode', '!=', 'disabled')->orderBy('category')->orderBy('name'),
        ])->orderBy('tier')->get();
        $keys = ['time_tracking', 'workspace_limit', 'csv_export', 'advanced_reports', 'excel_pdf_exports', 'earnings', 'scheduled_reports', 'team_members', 'timesheet_approvals', 'payroll_exports'];
        $comparison = Feature::whereIn('key', $keys)->where('mode', '!=', 'disabled')->get()->sortBy(fn ($feature) => array_search($feature->key, $keys))->values();
        $value = function ($plan, $feature): string {
            $mapped = $plan->features->firstWhere('id', $feature->id);
            if ($feature->mode === 'free' && $feature->value_type === 'boolean') {
                return 'Included';
            }
            if (! $mapped) {
                return '—';
            }
            $raw = $mapped->pivot->value;
            $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
            if ($feature->value_type === 'quota') {
                return $decoded === null ? 'Unlimited' : number_format((int) $decoded);
            }

            return $decoded ? 'Included' : '—';
        };

        return view('pricing', compact('plans', 'interval', 'comparison', 'value') + [
            'checkoutEnabled' => $settings->boolean('checkout_enabled'),
            'beta' => ! $settings->boolean('paid_enforcement_enabled'),
        ]);
    }
}
