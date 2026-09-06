<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class FaqController extends Controller
{
    public function __invoke(): View
    {
        return view('faq', [
            'items' => [
                ['question' => 'What is myhourspay?', 'answer' => 'myhourspay helps you record working hours, review weekly totals and prepare clear reports for yourself or your team.'],
                ['question' => 'Can I start for free?', 'answer' => 'Yes. The Free plan includes core time tracking and CSV export. Eligible accounts may also see a paid-plan trial at sign-up.'],
                ['question' => 'How do approved timesheets work?', 'answer' => 'Log hours for the week, submit the week for review, then an owner, administrator or manager can approve and lock it before payroll export.'],
                ['question' => 'Can I change or cancel my plan?', 'answer' => 'Manage your subscription from Plans & billing. Paid upgrades may apply immediately with proration. Downgrades and cancellation normally take effect at the end of the current trial or paid period.'],
                ['question' => 'What happens to my records if I downgrade?', 'answer' => 'Your records are preserved. The Free plan limits apply to future use, and your personal data export remains available.'],
                ['question' => 'How does myhourspay protect my data?', 'answer' => 'Access is protected by account authentication and workspace permissions. Read the privacy policy for the data we process, why we process it and how to contact us.'],
                ['question' => 'How do I contact support?', 'answer' => 'Email support@myhourspay.com with your account email and a short description of the issue.'],
            ],
        ]);
    }
}
