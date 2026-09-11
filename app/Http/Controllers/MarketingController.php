<?php

namespace App\Http\Controllers;

use App\Models\MarketingPreference;
use App\Models\Workspace;
use App\Services\MarketingConsent;
use Illuminate\Http\Request;

class MarketingController extends Controller
{
    public function preferences(Request $request)
    {
        return view('marketing.preferences', ['preference' => MarketingPreference::where('user_id', $request->user()->id)->first()]);
    }

    public function update(Request $request, MarketingConsent $consent)
    {
        $request->validate(['consented' => 'nullable|boolean']);
        $consent->set($request->user(), $request->boolean('consented'), 'account');

        return back()->with('status', 'Product tips & offers preference saved.');
    }

    public function dismiss(Request $request, MarketingConsent $consent)
    {
        $preference = MarketingPreference::where('user_id', $request->user()->id)->first()
            ?? $consent->set($request->user(), false, 'dismissed');
        $preference->update(['dismissed_at' => now('UTC')]);

        return back();
    }

    public function unsubscribe(Request $request, string $token, MarketingConsent $consent)
    {
        $preference = MarketingPreference::where('token', $token)->firstOrFail();
        if ($request->isMethod('post')) {
            if ($preference->user) {
                $consent->set($preference->user, false, 'unsubscribe');
            }

            return response()->view('marketing.unsubscribe', ['done' => true, 'token' => $token])->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
        }

        return response()->view('marketing.unsubscribe', ['done' => false, 'token' => $token])->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function open(Request $request, Workspace $workspace)
    {
        $destination = $request->query('destination');
        abort_unless(in_array($destination, ['hours.index', 'hours.reports.index', 'pro.clients.index', 'pro.schedules.index', 'business.timesheets.index', 'pro.invoices.index', 'billing.index']), 404);
        abort_unless((int) $workspace->owner_id === (int) $request->user()->id, 403);
        // Reuse the current workspace field. Destination middleware still enforces access.
        $request->user()->forceFill(['current_workspace_id' => $workspace->id])->save();

        return redirect()->route($destination);
    }
}
