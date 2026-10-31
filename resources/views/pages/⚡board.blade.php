<?php

use App\Actions\RecordActivityUpdate;
use App\Enums\ActivityStatus;
use App\Models\Activity;
use App\Models\ActivityUpdate;
use Flux\Flux;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Daily Board')] class extends Component
{
    #[Url]
    public string $date = '';

    #[Url]
    public string $filter = 'all';

    #[Url]
    public string $category = '';

    #[Url]
    public string $search = '';

    public ?int $selectedActivityId = null;

    public string $newStatus = 'done';

    public string $remark = '';

    public function mount(): void
    {
        if ($this->date === '') {
            $this->date = today()->toDateString();
        }
    }


    #[Computed]
public function day(): CarbonInterface
{
    try {
        $day = CarbonImmutable::parse($this->date)->startOfDay();
    } catch (\Throwable) {
        $day = today();
    }

    return $day->greaterThan(today()) ? today() : $day;
}

    #[Computed]
    public function isToday(): bool
    {
        return $this->day->isToday();
    }

    #[Computed]
    public function activities()
    {
        $day = $this->day;

        return Activity::query()
            ->where(function ($query) use ($day) {
                $query->where('is_active', true)
                    ->orWhereHas('updates', fn ($updates) => $updates->whereDate('activity_date', $day));
            })
            ->with(['updates' => fn ($query) => $query->whereDate('activity_date', $day)->with('user')->latest()])
            ->orderBy('category')
            ->orderBy('title')
            ->get();
    }

    #[Computed]
    public function carriedOver()
    {
        return ActivityUpdate::query()
            ->whereDate('activity_date', $this->day->copy()->subDay())
            ->with('user')
            ->orderBy('created_at')
            ->get()
            ->groupBy('activity_id')
            ->map(fn ($updates) => $updates->last())
            ->filter(fn ($update) => $update->status === ActivityStatus::Pending);
    }

    #[Computed]
    public function visibleActivities()
    {
        return $this->activities
            ->when($this->category !== '', fn ($list) => $list->where('category', $this->category))
            ->when($this->search !== '', fn ($list) => $list->filter(
                fn ($activity) => stripos($activity->title, $this->search) !== false
            ))
            ->filter(fn ($activity) => match ($this->filter) {
                'done' => $activity->updates->first()?->status === ActivityStatus::Done,
                'pending' => $activity->updates->first()?->status === ActivityStatus::Pending,
                'not_started' => $activity->updates->isEmpty(),
                default => true,
            });
    }

    #[Computed]
    public function stats(): array
    {
        $total = $this->activities->count();
        $done = $this->activities->filter(fn ($a) => $a->updates->first()?->status === ActivityStatus::Done)->count();
        $pending = $this->activities->filter(fn ($a) => $a->updates->first()?->status === ActivityStatus::Pending)->count();

        return [
            'total' => $total,
            'done' => $done,
            'pending' => $pending,
            'not_started' => $total - $done - $pending,
            'percent' => $total > 0 ? (int) round($done / $total * 100) : 0,
        ];
    }

    #[Computed]
    public function handover()
    {
        return $this->activities->filter(function ($activity) {
            $latest = $activity->updates->first();

            return $latest
                ? $latest->status === ActivityStatus::Pending
                : $this->carriedOver->has($activity->id);
        });
    }

    #[Computed]
    public function timeline()
    {
        return ActivityUpdate::query()
            ->whereDate('activity_date', $this->day)
            ->with(['user', 'activity'])
            ->latest()
            ->get();
    }

    #[Computed]
    public function selectedActivity(): ?Activity
    {
        return $this->selectedActivityId ? Activity::find($this->selectedActivityId) : null;
    }

    public function previousDay(): void
    {
        $this->date = $this->day->copy()->subDay()->toDateString();
    }

    public function nextDay(): void
    {
        if (! $this->isToday) {
            $this->date = $this->day->copy()->addDay()->toDateString();
        }
    }

    public function goToToday(): void
    {
        $this->date = today()->toDateString();
    }

    public function openUpdate(int $activityId, string $status): void
    {
        abort_unless($this->isToday, 403);

        $this->selectedActivityId = $activityId;
        $this->newStatus = $status;
        $this->remark = '';
        $this->resetValidation();

        Flux::modal('update-activity')->show();
    }

    public function saveUpdate(RecordActivityUpdate $recorder): void
    {
        $this->validate(
            [
                'newStatus' => ['required', Rule::enum(ActivityStatus::class)],
                'remark' => [$this->newStatus === 'pending' ? 'required' : 'nullable', 'string', 'max:1000'],
            ],
            [
                'remark.required' => 'Please explain why this is still pending, so the next person can pick it up.',
            ]
        );

        $activity = Activity::findOrFail($this->selectedActivityId);

        $recorder->handle($activity, auth()->user(), ActivityStatus::from($this->newStatus), $this->remark);

        Flux::modal('update-activity')->close();

        unset($this->activities, $this->timeline, $this->stats, $this->handover, $this->visibleActivities);

        $this->reset('selectedActivityId', 'remark');

        Flux::toast(variant: 'success', text: 'Update recorded.');
    }
};
?>

<div class="flex flex-col gap-6" @if ($this->isToday) wire:poll.30s @endif>

    {{-- Header and date navigation --}}
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl">Daily Board</flux:heading>
            <flux:text class="mt-1">
                {{ $this->day->format('l, j F Y') }}
                @if ($this->isToday)
                    <span class="ms-2 inline-flex items-center gap-1 text-green-600 dark:text-green-400">
                        <span class="size-2 animate-pulse rounded-full bg-green-500"></span> Live
                    </span>
                @endif
            </flux:text>
        </div>

        <div class="flex items-center gap-2">
            <flux:button size="sm" icon="chevron-left" wire:click="previousDay" aria-label="Previous day" />
            <flux:input size="sm" type="date" wire:model.live="date" max="{{ today()->toDateString() }}" class="w-40" />
            <flux:button size="sm" icon="chevron-right" wire:click="nextDay" :disabled="$this->isToday" aria-label="Next day" />
            @unless ($this->isToday)
                <flux:button size="sm" variant="primary" wire:click="goToToday">Today</flux:button>
            @endunless
        </div>
    </div>

    @unless ($this->isToday)
        <div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-200">
            You are viewing a past day. Updates are read-only to protect the activity history.
        </div>
    @endunless

    {{-- Summary --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text>Activities</flux:text>
            <p class="mt-1 text-3xl font-semibold">{{ $this->stats['total'] }}</p>
        </div>
        <div class="rounded-xl border border-green-200 bg-green-50 p-4 dark:border-green-900 dark:bg-green-950">
            <flux:text>Done</flux:text>
            <p class="mt-1 text-3xl font-semibold text-green-700 dark:text-green-400">{{ $this->stats['done'] }}</p>
        </div>
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950">
            <flux:text>Pending</flux:text>
            <p class="mt-1 text-3xl font-semibold text-amber-700 dark:text-amber-400">{{ $this->stats['pending'] }}</p>
        </div>
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text>Not started</flux:text>
            <p class="mt-1 text-3xl font-semibold text-zinc-500">{{ $this->stats['not_started'] }}</p>
        </div>
    </div>

    <div>
        <div class="mb-1 flex justify-between text-sm">
            <flux:text>Completion</flux:text>
            <flux:text>{{ $this->stats['percent'] }}%</flux:text>
        </div>
        <div class="h-2 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
            <div class="h-full rounded-full bg-green-500 transition-all duration-500" style="width: {{ $this->stats['percent'] }}%"></div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">

        {{-- Activities --}}
        <div class="flex flex-col gap-4 lg:col-span-2">

            <div class="flex flex-wrap gap-2">
                @foreach (['all' => 'All', 'pending' => 'Pending', 'done' => 'Done', 'not_started' => 'Not started'] as $key => $label)
                    <flux:button size="sm" :variant="$filter === $key ? 'primary' : 'ghost'" wire:click="$set('filter', '{{ $key }}')">
                        {{ $label }}
                    </flux:button>
                @endforeach
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <flux:input size="sm" icon="magnifying-glass" placeholder="Search activities" wire:model.live.debounce.300ms="search" />
                <flux:select size="sm" wire:model.live="category">
                    <option value="">All categories</option>
                    @foreach (\App\Models\Activity::CATEGORIES as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </flux:select>
            </div>

            @forelse ($this->visibleActivities as $activity)
                @php
                    $latest = $activity->updates->first();
                    $carried = ! $latest ? $this->carriedOver->get($activity->id) : null;
                @endphp

                <div wire:key="activity-{{ $activity->id }}" class="rounded-xl border border-zinc-200 p-4 transition hover:shadow-sm dark:border-zinc-700">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:badge size="sm">{{ $activity->category }}</flux:badge>
                                @if ($carried)
                                    <flux:badge size="sm" color="orange" icon="arrow-uturn-right">Carried over</flux:badge>
                                @endif
                                @unless ($activity->is_active)
                                    <flux:badge size="sm" color="zinc">Retired</flux:badge>
                                @endunless
                            </div>

                            <h3 class="mt-2 font-semibold">{{ $activity->title }}</h3>

                            @if ($latest)
                                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ $latest->status->label() }} by <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ $latest->user->name }}</span>
                                    at {{ $latest->created_at->format('H:i') }}
                                </p>
                                @if ($latest->remark)
                                    <p class="mt-2 rounded-md bg-zinc-50 px-3 py-2 text-sm dark:bg-zinc-800">{{ $latest->remark }}</p>
                                @endif
                            @elseif ($carried)
                                <p class="mt-1 text-sm text-orange-700 dark:text-orange-400">
                                    Left pending yesterday by {{ $carried->user->name }} at {{ $carried->created_at->format('H:i') }}
                                </p>
                                @if ($carried->remark)
                                    <p class="mt-2 rounded-md bg-orange-50 px-3 py-2 text-sm dark:bg-orange-950">{{ $carried->remark }}</p>
                                @endif
                            @else
                                <p class="mt-1 text-sm text-zinc-500">No updates {{ $this->isToday ? 'yet today' : 'on this day' }}.</p>
                            @endif
                        </div>

                        @if ($latest)
                            <flux:badge :color="$latest->status->color()">{{ $latest->status->label() }}</flux:badge>
                        @else
                            <flux:badge color="zinc">Not started</flux:badge>
                        @endif
                    </div>

                    @if ($this->isToday && $activity->is_active)
                        <div class="mt-4 flex gap-2">
                            <flux:button size="sm" icon="check" wire:click="openUpdate({{ $activity->id }}, 'done')">Mark done</flux:button>
                            <flux:button size="sm" variant="ghost" icon="clock" wire:click="openUpdate({{ $activity->id }}, 'pending')">Mark pending</flux:button>
                        </div>
                    @endif

                    @if ($activity->updates->count() > 1)
                        <details class="mt-4">
                            <summary class="cursor-pointer text-sm text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200">
                                View all {{ $activity->updates->count() }} updates
                            </summary>
                            <ul class="mt-3 space-y-2 border-s border-zinc-200 ps-4 dark:border-zinc-700">
                                @foreach ($activity->updates as $update)
                                    <li class="text-sm">
                                        <span class="text-zinc-500">{{ $update->created_at->format('H:i') }}</span>
                                        <span class="font-medium">{{ $update->user->name }}</span>
                                        marked it <span class="font-medium">{{ $update->status->label() }}</span>
                                        @if ($update->remark)
                                            <span class="text-zinc-500">— {{ $update->remark }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-zinc-300 p-8 text-center text-sm text-zinc-500 dark:border-zinc-700">
                    No activities match these filters.
                </div>
            @endforelse
        </div>

        {{-- Side panels --}}
        <div class="flex flex-col gap-6">

            <div class="rounded-xl border border-orange-200 p-4 dark:border-orange-900">
                <div class="flex items-center justify-between">
                    <flux:heading>Pending handover</flux:heading>
                    <flux:badge color="orange">{{ $this->handover->count() }}</flux:badge>
                </div>
                <flux:text class="mt-1">Items the next person needs to pick up.</flux:text>

                <ul class="mt-4 space-y-3">
                    @forelse ($this->handover as $activity)
                        @php $source = $activity->updates->first() ?? $this->carriedOver->get($activity->id); @endphp
                        <li wire:key="handover-{{ $activity->id }}" class="text-sm">
                            <p class="font-medium">{{ $activity->title }}</p>
                            <p class="text-zinc-500">
                                {{ $source->user->name }}, {{ $source->created_at->format($activity->updates->isEmpty() ? 'D H:i' : 'H:i') }}
                                @if ($activity->updates->isEmpty()) <span class="text-orange-600">(from yesterday)</span> @endif
                            </p>
                            @if ($source->remark)
                                <p class="mt-1 text-zinc-600 dark:text-zinc-300">{{ $source->remark }}</p>
                            @endif
                        </li>
                    @empty
                        <li class="text-sm text-green-700 dark:text-green-400">Nothing pending. All clear.</li>
                    @endforelse
                </ul>
            </div>

            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:heading>Timeline</flux:heading>
                <flux:text class="mt-1">Every update on this day, newest first.</flux:text>

                <ol class="relative mt-4 border-s border-zinc-200 dark:border-zinc-700">
                    @forelse ($this->timeline as $update)
                        <li wire:key="timeline-{{ $update->id }}" class="mb-4 ms-4">
                            <span class="absolute -start-1.5 mt-1.5 size-3 rounded-full {{ $update->status === \App\Enums\ActivityStatus::Done ? 'bg-green-500' : 'bg-amber-500' }}"></span>
                            <time class="text-xs text-zinc-500">{{ $update->created_at->format('H:i') }}</time>
                            <p class="text-sm">
                                <span class="font-medium">{{ $update->user->name }}</span>
                                marked <span class="font-medium">{{ $update->activity->title }}</span>
                                as {{ strtolower($update->status->label()) }}
                            </p>
                            @if ($update->remark)
                                <p class="mt-1 text-sm text-zinc-500">{{ $update->remark }}</p>
                            @endif
                        </li>
                    @empty
                        <li class="ms-4 text-sm text-zinc-500">No updates on this day.</li>
                    @endforelse
                </ol>
            </div>
        </div>
    </div>

    {{-- Update form --}}
    <flux:modal name="update-activity" class="md:w-[28rem]">
        <form wire:submit="saveUpdate" class="space-y-5">
            <div>
                <flux:heading size="lg">Update activity</flux:heading>
                <flux:text class="mt-1">{{ $this->selectedActivity?->title }}</flux:text>
            </div>

            <flux:select wire:model.live="newStatus" :label="__('Status')">
                <option value="done">Done</option>
                <option value="pending">Pending</option>
            </flux:select>

            <flux:textarea
                wire:model="remark"
                :label="$newStatus === 'pending' ? __('Remark (required)') : __('Remark (optional)')"
                :placeholder="$newStatus === 'pending' ? 'What is blocking this, and what should the next person do?' : 'e.g. SMS count matched logs: 12,430'"
                rows="3"
            />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Save update</flux:button>
            </div>
        </form>
    </flux:modal>
</div>

