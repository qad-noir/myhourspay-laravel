<?php

namespace App\Http\Controllers;

use App\Services\EmailVerificationCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationCodeController extends Controller
{
    public function show(Request $request, EmailVerificationCodeService $codes): View|RedirectResponse
    {
        if ($request->user()->email_verified_at) {
            return to_route('dashboard');
        }

        if (! $request->user()->emailVerificationCode()->exists()) {
            $codes->issue($request->user());
        }

        return view('auth.verify-code');
    }

    public function verify(Request $request, EmailVerificationCodeService $codes): RedirectResponse
    {
        $validated = $request->validate([
            'digits' => ['required', 'array', 'size:6'],
            'digits.*' => ['required', 'digits:1'],
        ]);
        $code = implode('', $validated['digits']);

        if (! $codes->verify($request->user(), $code)) {
            return back()->withErrors(['code' => 'That code is incorrect or has expired. Request a new code and try again.']);
        }

        return to_route('dashboard')->with('status', 'Email verified successfully.');
    }

    public function resend(Request $request, EmailVerificationCodeService $codes): RedirectResponse
    {
        if ($request->user()->email_verified_at) {
            return to_route('dashboard');
        }

        if (! $codes->canResend($request->user())) {
            return back()->withErrors(['resend' => 'Please wait before requesting another code.']);
        }

        $codes->issue($request->user());

        return back()->with('status', 'A new verification code has been sent.');
    }
}
