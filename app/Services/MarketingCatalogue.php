<?php

namespace App\Services;

use App\Models\MarketingCampaign;

class MarketingCatalogue
{
    public const AUDIENCES = ['hours', 'projects', 'schedules', 'reports', 'workflow', 'plans'];

    public function install(): void
    {
        $steps = [
            ['hours', 1, 'Your first workday, recorded', 'A clear record starts with one day.', 'Start with the hours you worked', 'Add a workday, enter your start and finish times, and choose your break. MyHoursPay keeps the record ready for your weekly review. Need a hand getting started? Our support team can help.'],
            ['projects', 4, 'Give your hours a project', 'Keep client work easy to find.', 'From recorded hours to organised work', 'Clients and projects give your records context. Explore how project assignments and rates can help you organise work and understand billable time.'],
            ['schedules', 8, 'Make regular work easier to record', 'Meet recurring schedules.', 'A starting point for your regular week', 'Expected schedules help you prepare regular workdays, then review and convert them into recorded hours. Explore recurring schedules and see whether they fit your working week.'],
            ['reports', 13, 'See the story of your working hours', 'Review a period and export your records.', 'Your hours, ready to review', 'Choose a date range to review your hours and download a CSV report. Other export options depend on your plan. Start with the records you have already created.'],
            ['workflow', 20, 'Take the next step with your hours', 'Discover a useful workflow for your workspace.', 'Put your recorded work to use', 'Explore the next step for your workspace. Team owners can review timesheets, while solo owners can turn billable records into invoices. Availability depends on your plan.'],
            ['plans', 30, 'Find the MyHoursPay plan that fits', 'Compare the tools available for your work.', 'Choose the tools you need', 'Compare the current plans and choose the features that fit your work. You can review what is included before deciding whether to upgrade.'],
        ];
        foreach ($steps as [$audience, $day, $subject, $preheader, $heading, $body]) {
            MarketingCampaign::firstOrCreate(['key' => 'intro-'.$audience], compact('audience', 'day', 'subject', 'preheader', 'heading', 'body') + ['kind' => 'intro', 'status' => 'paused']);
        }
    }
}
