<?php


use App\Enums\ActivityStatus;
use App\Models\Activity;
use App\Models\User;
use App\Reports\ActivityReport;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;


new #[Title('Reports')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $userId = '';

    #[Url]
    public string $activityId = '';

    #[Url]
    public string $category = '';

    #[Url]
    public string $status = '';

    public function mount(): void
    {
        $this->from = $this->from !== '' ? $this->from : today()->subDays(6)->toDateString();
        $this->to = $this->to !== '' ? $this->to : today()->toDateString();
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function applyPreset(string $preset): void
    {
        [$from, $to] = match ($preset) {
            'today' => [today(), today()],
            'yesterday' => [today()->subDay(), today()->subDay()],
            'last_30' => [today()->subDays(29), today()],
            'this_month' => [today()->startOfMonth(), today()],
            'last_month' => [today()->subMonthNoOverflow()->startOfMonth(), today()->subMonthNoOverflow()->endOfMonth()],
            default => [today()->subDays(6), today()],
        };

        $this->from = $from->toDateString();
        $this->to = $to->toDateString();
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('userId', 'activityId', 'category', 'status');
        $this->resetPage();
    }

    #[Computed]
    public function report(): ActivityReport
    {
        $from = $this->parseDate($this->from, today()->subDays(6));
        $to = $this->parseDate($this->to, today());

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        if ($from->diffInDays($to) > 365) {
            $from = $to->copy()->subDays(365);
        }

        return new ActivityReport(
            from: $from,
            to: $to,
            userId: $this->userId !== '' ? (int) $this->userId : null,
            activityId: $this->activityId !== '' ? (int) $this->activityId : null,
            category: $this->category !== '' ? $this->category : null,
            status: ActivityStatus::tryFrom($this->status),
        );
    }

    #[Computed]
    public function updates()
    {
        return $this->report->query()->paginate(20);
    }

    #[Computed]
    public function users()
    {
        return User::orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function activities()
    {
        return Activity::withTrashed()->orderBy('title')->get(['id', 'title']);
    }

    public function export(): StreamedResponse
    {
        $report = $this->report;

        $filename = sprintf('activity-report_%s_to_%s.csv', $report->from->toDateString(), $report->to->toDateString());

        return response()->streamDownload(function () use ($report) {
            $handle = fopen('php://output', 'w');
            $report->writeCsv($handle);
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function parseDate(string $value, CarbonInterface $fallback): CarbonInterface
    {
        try {
            $date = CarbonImmutable::parse($value)->startOfDay();
        } catch (\Throwable) {
            return $fallback;
        }

        return $date->greaterThan(today()) ? today() : $date;
    }
};
?>

<div class="flex flex-col gap-6">

    {{-- Header --}}
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Reports</flux:heading>
            <flux:text class="mt-1">
                Activity history from {{ $this->report->from->format('j M Y') }} to {{ $this->report->to->format('j M Y') }}
            </flux:text>
        </div>
        <div class="flex gap-2 print:hidden">
            <flux:button size="sm" icon="printer" x-on:click="window.print()">Print</flux:button>
            <flux:button size="sm" variant="primary" icon="arrow-down-tray" wire:click="export">Export CSV</flux:button>
        </div>
    </div>

    {{-- Filters --}}
    <div class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-4 print:hidden dark:border-zinc-700">
        <div class="flex flex-wrap gap-2">
            @foreach (['today' => 'Today', 'yesterday' => 'Yesterday', 'last_7' => 'Last 7 days', 'last_30' => 'Last 30 days', 'this_month' => 'This month', 'last_month' => 'Last month'] as $key => $label)
                <flux:button size="sm" variant="ghost" wire:click="applyPreset('{{ $key }}')">{{ $label }}</flux:button>
            @endforeach
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <flux:input size="sm" type="date" label="From" wire:model.live="from" max="{{ today()->toDateString() }}" />
            <flux:input size="sm" type="date" label="To" wire:model.live="to" max="{{ today()->toDateString() }}" />

            <flux:select size="sm" label="Person" wire:model.live="userId">
                <option value="">Everyone</option>
                @foreach ($this->users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                @endforeach
            </flux:select>

            <flux:select size="sm" label="Activity" wire:model.live="activityId">
                <option value="">All activities</option>
                @foreach ($this->activities as $activity)
                    <option value="{{ $activity->id }}">{{ $activity->title }}</option>
                @endforeach
            </flux:select>

            <flux:select size="sm" label="Category" wire:model.live="category">
                <option value="">All categories</option>
                @foreach (\App\Models\Activity::CATEGORIES as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </flux:select>

            <flux:select size="sm" label="Status" wire:model.live="status">
                <option value="">Any status</option>
                <option value="done">Done</option>
                <option value="pending">Pending</option>
            </flux:select>
        </div>

        <div>
            <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="clearFilters">Clear filters</flux:button>
        </div>
    </div>

    {{-- Summary --}}
    @php $summary = $this->report->summary(); @endphp
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text>Updates</flux:text>
            <p class="mt-1 text-3xl font-semibold">{{ $summary['total'] }}</p>
        </div>
        <div class="rounded-xl border border-green-200 bg-green-50 p-4 dark:border-green-900 dark:bg-green-950">
            <flux:text>Marked done</flux:text>
            <p class="mt-1 text-3xl font-semibold text-green-700 dark:text-green-400">{{ $summary['done'] }}</p>
        </div>
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950">
            <flux:text>Marked pending</flux:text>
            <p class="mt-1 text-3xl font-semibold text-amber-700 dark:text-amber-400">{{ $summary['pending'] }}</p>
        </div>
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text>People</flux:text>
            <p class="mt-1 text-3xl font-semibold">{{ $summary['people'] }}</p>
        </div>
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text>Activities</flux:text>
            <p class="mt-1 text-3xl font-semibold">{{ $summary['activities'] }}</p>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">

        {{-- Daily chart --}}
        @php
            $daily = $this->report->daily();
            $max = max(1, max(array_map(fn ($day) => $day['done'] + $day['pending'], $daily)));
            $dates = array_keys($daily);
        @endphp
        <div class="rounded-xl border border-zinc-200 p-4 lg:col-span-2 dark:border-zinc-700">
            <div class="flex items-center justify-between">
                <flux:heading>Updates per day</flux:heading>
                <div class="flex gap-4 text-xs text-zinc-500">
                    <span class="flex items-center gap-1"><span class="size-2.5 rounded-sm bg-green-500"></span> Done</span>
                    <span class="flex items-center gap-1"><span class="size-2.5 rounded-sm bg-amber-400"></span> Pending</span>
                </div>
            </div>

            <div class="mt-4 flex h-48 items-end gap-1">
                @foreach ($daily as $date => $counts)
                    <div class="flex h-full flex-1 flex-col justify-end"
                         title="{{ \Carbon\Carbon::parse($date)->format('D j M') }}: {{ $counts['done'] }} done, {{ $counts['pending'] }} pending">
                        <div class="w-full rounded-t-sm bg-amber-400" style="height: {{ round($counts['pending'] / $max * 100, 2) }}%"></div>
                        <div class="w-full bg-green-500" style="height: {{ round($counts['done'] / $max * 100, 2) }}%"></div>
                    </div>
                @endforeach
            </div>

            <div class="mt-2 flex justify-between text-xs text-zinc-500">
                <span>{{ \Carbon\Carbon::parse($dates[0])->format('j M') }}</span>
                @if (count($dates) > 2)
                    <span>{{ \Carbon\Carbon::parse($dates[intdiv(count($dates), 2)])->format('j M') }}</span>
                @endif
                <span>{{ \Carbon\Carbon::parse(end($dates))->format('j M') }}</span>
            </div>
        </div>

        {{-- Insights --}}
        <div class="flex flex-col gap-6">
            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:heading>By person</flux:heading>
                <ul class="mt-3 space-y-2">
                    @forelse ($this->report->byPerson() as $person)
                        <li class="flex items-center justify-between text-sm">
                            <span class="font-medium">{{ $person->name }}</span>
                            <span class="text-zinc-500">
                                {{ $person->total }} updates
                                <span class="text-green-600 dark:text-green-400">· {{ $person->done }} done</span>
                                <span class="text-amber-600 dark:text-amber-400">· {{ $person->pending }} pending</span>
                            </span>
                        </li>
                    @empty
                        <li class="text-sm text-zinc-500">No updates in this period.</li>
                    @endforelse
                </ul>
            </div>

            <div class="rounded-xl border border-orange-200 p-4 dark:border-orange-900">
                <flux:heading>Most often left pending</flux:heading>
                <flux:text class="mt-1">Checks that may need attention or a process fix.</flux:text>
                <ol class="mt-3 space-y-2">
                    @forelse ($this->report->mostPending() as $item)
                        <li class="flex items-center justify-between gap-3 text-sm">
                            <span>{{ $item->title }}</span>
                            <flux:badge size="sm" color="orange">{{ $item->total }}</flux:badge>
                        </li>
                    @empty
                        <li class="text-sm text-zinc-500">Nothing left pending.</li>
                    @endforelse
                </ol>
            </div>
        </div>
    </div>

    {{-- History table --}}
    <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-zinc-50 text-xs uppercase text-zinc-500 dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Time</th>
                        <th class="px-4 py-3">Activity</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Remark</th>
                        <th class="px-4 py-3">Updated by</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($this->updates as $update)
                        <tr wire:key="row-{{ $update->id }}" class="align-top">
                            <td class="whitespace-nowrap px-4 py-3">{{ $update->activity_date->format('D j M') }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-zinc-500">{{ $update->created_at->format('H:i') }}</td>
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ $update->activity->title }}</p>
                                <p class="text-xs text-zinc-500">{{ $update->activity->category }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <flux:badge size="sm" :color="$update->status->color()">{{ $update->status->label() }}</flux:badge>
                            </td>
                            <td class="max-w-xs px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ $update->remark ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <p>{{ $update->user->name }}</p>
                                <p class="text-xs text-zinc-500">{{ $update->user->staff_id }}</p>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-zinc-500">No updates match these filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="print:hidden">
        {{ $this->updates->links() }}
    </div>
</div>