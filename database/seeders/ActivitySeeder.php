<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Database\Seeder;

class ActivitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $adminId = User::where('email', 'admin@example.com')->value('id');

        $activities = [
            ['title' => 'Daily SMS count vs SMS count from logs', 'category' => 'SMS', 'description' => 'Compare the SMS count on the dashboard with the count in the gateway logs. Escalate if the difference is above 1%.'],
            ['title' => 'Check SMS delivery failure rate', 'category' => 'SMS', 'description' => 'Review failed and undelivered SMS for the past 24 hours and identify affected networks.'],
            ['title' => 'Check USSD service uptime', 'category' => 'USSD', 'description' => 'Dial each live USSD code and confirm menus load within normal response time.'],
            ['title' => 'Review USSD session timeouts', 'category' => 'USSD', 'description' => 'Check session timeout counts and flag any spike compared with the previous day.'],
            ['title' => 'Review failed transactions', 'category' => 'Payments', 'description' => 'Pull failed mobile money transactions and confirm reversals have been processed.'],
            ['title' => 'Verify payment gateway response times', 'category' => 'Payments', 'description' => 'Confirm average response time is within the agreed limit for each gateway.'],
            ['title' => 'Monitor server disk space', 'category' => 'Infrastructure', 'description' => 'Check disk usage on all production servers. Raise a ticket for anything above 80%.'],
            ['title' => 'Confirm overnight backups ran', 'category' => 'Infrastructure', 'description' => 'Verify database and file backups completed successfully and are restorable.'],
            ['title' => 'Check API error logs', 'category' => 'Monitoring', 'description' => 'Review 5xx errors from the past 24 hours and group them by endpoint.'],
            ['title' => 'Review open support tickets', 'category' => 'Support', 'description' => 'Go through open client tickets, update statuses and flag any breaching SLA.'],
            ['title' => 'Legacy fax gateway check', 'category' => 'Infrastructure', 'description' => 'Retired service. Kept to demonstrate inactive activities.', 'is_active' => false],
        ];

        foreach ($activities as $activity) {
            Activity::updateOrCreate(
                ['title' => $activity['title']],
                [
                    'category' => $activity['category'],
                    'description' => $activity['description'],
                    'is_active' => $activity['is_active'] ?? true,
                    'created_by' => $adminId,
                ]
            );
        }
    }
}
