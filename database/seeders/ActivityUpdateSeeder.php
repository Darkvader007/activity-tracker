<?php

namespace Database\Seeders;

use App\Enums\ActivityStatus;
use App\Models\Activity;
use App\Models\ActivityUpdate;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;

class ActivityUpdateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (ActivityUpdate::exists()) {
            return;
        }
        //
        $staff = User::role('support')->get();
        $activities = Activity::active()->get();

        $pendingRemarks = [
            'Started check, waiting on log export from the gateway team.',
            'Logs not yet available, will retry after 10am.',
            'Partial results in. Handing over to the next shift to complete.',
            'Discrepancy found, escalated to the vendor. Awaiting response.',
            'System slow this morning, check in progress.',
        ];

        $doneRemarks = [
            'Completed. Figures match, no issues found.',
            'Done. Minor difference within acceptable range.',
            'All services responding normally.',
            'Checked and confirmed. Nothing to escalate.',
            'Resolved after vendor fix. Verified working.',
        ];

        for ($daysAgo = 13; $daysAgo >= 0; $daysAgo--) {
            $date = today()->subDays($daysAgo);

            foreach ($activities as $activity) {
                if ($daysAgo > 0 && rand(1, 10) === 1) {
                    continue;
                }

                $firstPerson = $staff->random();
                $startedAt = $date->copy()->setTime(rand(7, 10), rand(0, 59));

                $this->record($activity, $firstPerson, ActivityStatus::Pending, $pendingRemarks[array_rand($pendingRemarks)], $date, $startedAt);

                $finishes = $daysAgo > 0 ? rand(1, 10) <= 8 : rand(1, 10) <= 5;

                if ($finishes) {
                    $finisher = rand(1, 10) <= 6 ? $firstPerson : $staff->random();
                    $finishedAt = $startedAt->copy()->addHours(rand(1, 6))->addMinutes(rand(0, 59));

                    $this->record($activity, $finisher, ActivityStatus::Done, $doneRemarks[array_rand($doneRemarks)], $date, $finishedAt);
                }
            }

        }
    }

    private function record(Activity $activity, User $user, ActivityStatus $status, string $remark, CarbonInterface $date, CarbonInterface $time): void
    {
        $update = new ActivityUpdate([
            'activity_id' => $activity->id,
            'user_id' => $user->id,
            'status' => $status,
            'remark' => $remark,
            'activity_date' => $date,
        ]);

        $update->created_at = $time;
        $update->updated_at = $time;
        $update->save();
    }
}
