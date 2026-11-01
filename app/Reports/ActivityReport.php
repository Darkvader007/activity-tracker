<?php

namespace App\Reports;

use App\Enums\ActivityStatus;
use App\Models\Activity;
use App\Models\ActivityUpdate;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ActivityReport
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        public readonly CarbonInterface $from,
        public readonly CarbonInterface $to,
        public readonly ?int $userId = null,
        public readonly ?int $activityId = null,
        public readonly ?string $category = null,
        public readonly ?ActivityStatus $status = null,
    ) {}

    /** @return Builder<ActivityUpdate> */
    public function query(): Builder
    {
        return $this->baseQuery()
            ->with(['user', 'activity'])
            ->orderByDesc('activity_updates.activity_date')
            ->orderByDesc('activity_updates.created_at')
            ->orderByDesc('activity_updates.id');
    }

    /** @return array{total: int, done: int, pending: int, people: int, activities: int} */
    public function summary(): array
    {
        return [
            'total' => $this->baseQuery()->count(),
            'done' => $this->baseQuery()->where('activity_updates.status', ActivityStatus::Done->value)->count(),
            'pending' => $this->baseQuery()->where('activity_updates.status', ActivityStatus::Pending->value)->count(),
            'people' => $this->baseQuery()->distinct()->count('activity_updates.user_id'),
            'activities' => $this->baseQuery()->distinct()->count('activity_updates.activity_id'),
        ];
    }

    /** @return array<string, array{done: int, pending: int}> */
    public function daily(): array
    {
        $days = [];

        foreach (CarbonPeriod::create($this->from, $this->to) as $day) {
            $days[$day->toDateString()] = ['done' => 0, 'pending' => 0];
        }

        $rows = $this->baseQuery()
            ->toBase()
            ->selectRaw('activity_updates.activity_date as day, activity_updates.status as status, COUNT(*) as total')
            ->groupBy('activity_updates.activity_date', 'activity_updates.status')
            ->get();

        foreach ($rows as $row) {
            $date = substr((string) $row->day, 0, 10);
            $status = $row->status === ActivityStatus::Done->value ? 'done' : 'pending';

            if (isset($days[$date])) {
                $days[$date][$status] += (int) $row->total;
            }
        }

        return $days;
    }

    /** @return Collection<int, \stdClass> */
    public function byPerson(): Collection
    {
        return $this->baseQuery()
            ->toBase()
            ->join('users', 'users.id', '=', 'activity_updates.user_id')
            ->selectRaw('users.name as name, COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN activity_updates.status = ? THEN 1 ELSE 0 END) as done', [ActivityStatus::Done->value])
            ->selectRaw('SUM(CASE WHEN activity_updates.status = ? THEN 1 ELSE 0 END) as pending', [ActivityStatus::Pending->value])
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total')
            ->get();
    }

    /** @return Collection<int, \stdClass> */
    public function mostPending(int $limit = 5): Collection
    {
        return $this->baseQuery()
            ->toBase()
            ->join('activities', 'activities.id', '=', 'activity_updates.activity_id')
            ->where('activity_updates.status', ActivityStatus::Pending->value)
            ->selectRaw('activities.title as title, COUNT(*) as total')
            ->groupBy('activities.id', 'activities.title')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();
    }

    /** @param resource $handle */
    public function writeCsv($handle): void
    {
        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, ['Date', 'Time', 'Activity', 'Category', 'Status', 'Remark', 'Updated by', 'Staff ID', 'Department'], ',', '"', '');

        foreach ($this->query()->lazy(500) as $update) {
            fputcsv($handle, [
                $update->activity_date->format('Y-m-d'),
                $update->created_at?->format('H:i') ?? '',
                $this->safe($update->activity?->title),
                $this->safe($update->activity?->category),
                $update->status->label(),
                $this->safe($update->remark),
                $this->safe($update->user?->name),
                $this->safe($update->user?->staff_id),
                $this->safe($update->user?->department),
            ], ',', '"', '');
        }
    }

    /** @return Builder<ActivityUpdate> */
    private function baseQuery(): Builder
    {
        $query = ActivityUpdate::query()
            ->whereDate('activity_updates.activity_date', '>=', $this->from->toDateString())
            ->whereDate('activity_updates.activity_date', '<=', $this->to->toDateString());

        if ($this->userId !== null) {
            $query->where('activity_updates.user_id', $this->userId);
        }

        if ($this->activityId !== null) {
            $query->where('activity_updates.activity_id', $this->activityId);
        }

        if ($this->status !== null) {
            $query->where('activity_updates.status', $this->status->value);
        }

        if ($this->category !== null) {
            $query->whereIn(
                'activity_updates.activity_id',
                Activity::withTrashed()->where('category', $this->category)->select('id')
            );
        }

        return $query;
    }

    private function safe(?string $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@'], true) ? "'".$value : $value;
    }
}
