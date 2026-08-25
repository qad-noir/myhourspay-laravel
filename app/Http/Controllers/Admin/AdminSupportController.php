<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportRequest;
use App\Services\AdminAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminSupportController extends Controller
{
    public function index(Request $request): View
    {
        $query = SupportRequest::query()->with(['user', 'workspace'])->latest();
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->query('priority'));
        }
        if ($request->filled('q')) {
            $query->where(fn ($builder) => $builder->where('subject', 'like', '%'.$request->query('q').'%')->orWhere('public_id', 'like', $request->query('q').'%')->orWhereHas('user', fn ($user) => $user->where('email', 'like', $request->query('q').'%')));
        }

        return view('admin.support.index', ['requests' => $query->paginate(30)->withQueryString()]);
    }

    public function show(SupportRequest $supportRequest): View
    {
        return view('admin.support.show', ['support' => $supportRequest->load(['user', 'workspace'])]);
    }

    public function update(Request $request, SupportRequest $supportRequest, AdminAudit $audit): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'in_progress', 'closed'])], 'priority' => ['required', Rule::in(['normal', 'priority', 'urgent'])]]);
        $before = $supportRequest->only(['status', 'priority', 'closed_at']);
        $supportRequest->update([...$data, 'closed_at' => $data['status'] === 'closed' ? now() : null]);
        $audit->record($request, 'support.updated', $supportRequest, $before, $supportRequest->only(['status', 'priority', 'closed_at']));

        return back()->with('status', 'Support queue item updated.');
    }
}
