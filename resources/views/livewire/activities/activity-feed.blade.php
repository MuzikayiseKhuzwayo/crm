<div class="crm-content">
    {{-- HEADER --}}
    <x-mary-header title="{{ ucfirst(__('laravel-crm::lang.activities')) }}" separator>
        <x-slot:actions>
            <x-mary-button
                label="{{ ucfirst(__('laravel-crm::lang.my_activity')) }}"
                wire:click="setScope('mine')"
                class="btn-sm {{ $scope === 'mine' ? 'btn-primary text-white' : 'btn-ghost' }}"
            />
            <x-mary-button
                label="{{ ucfirst(__('laravel-crm::lang.all_activity')) }}"
                wire:click="setScope('all')"
                class="btn-sm {{ $scope === 'all' ? 'btn-primary text-white' : 'btn-ghost' }}"
            />
        </x-slot:actions>
    </x-mary-header>

    {{-- FILTERS BAR --}}
    <x-mary-card shadow class="mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            {{-- ENTITY TYPE FILTER ("WHERE IT COMES FROM") --}}
            <div>
                <label class="block text-xs font-semibold text-base-content/80 mb-1">Filter by Origin Entity:</label>
                <select wire:model.live="entityType" class="select select-bordered select-sm w-full text-xs">
                    @foreach($entityTypeOptions as $opt)
                        <option value="{{ $opt['id'] }}">{{ $opt['name'] }}</option>
                    @endforeach
                </select>
            </div>

            {{-- USER / AUTHOR FILTER --}}
            <div>
                <label class="block text-xs font-semibold text-base-content/80 mb-1">Logged By User:</label>
                <select wire:model.live="user_id" class="select select-bordered select-sm w-full text-xs">
                    <option value="">All Users</option>
                    @foreach($userOptions as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- DATE PRESET FILTER --}}
            <div>
                <label class="block text-xs font-semibold text-base-content/80 mb-1">Logged Date:</label>
                <select wire:model.live="datePreset" class="select select-bordered select-sm w-full text-xs">
                    @foreach($datePresets as $dp)
                        <option value="{{ $dp['id'] }}">{{ $dp['name'] }}</option>
                    @endforeach
                </select>
            </div>

            {{-- CLEAR FILTERS BUTTON --}}
            <div>
                @if($entityType || $user_id || $datePreset || $search)
                    <button wire:click="clearFilters" class="btn btn-sm btn-ghost text-error w-full gap-1">
                        <x-mary-icon name="o-x-mark" class="w-4 h-4" />
                        <span>Clear Filters</span>
                    </button>
                @endif
            </div>
        </div>
    </x-mary-card>

    {{-- ACTIVITY TYPE TABS --}}
    <x-mary-tabs wire:model.live="tab">
        @foreach(['all' => 'all', 'notes' => 'notes', 'tasks' => 'tasks', 'calls' => 'calls', 'meetings' => 'meetings', 'lunches' => 'lunches', 'files' => 'files'] as $tabName => $langKey)
            <x-mary-tab name="{{ $tabName }}" label="{{ ucfirst(__('laravel-crm::lang.' . $langKey)) }}">
                @if($tab === $tabName)
                    <x-mary-card shadow>
                        @forelse($this->activities as $activity)
                            @php
                                $userName = $activity->causeable->name ?? $activity->recordable?->createdByUser?->name ?? null;
                                $activityType = $activity->recordable_type ? strtolower(class_basename($activity->recordable_type)) : null;
                                $isTaskActivity = ($activity->timelineable instanceof \VentureDrake\LaravelCrm\Models\Task 
                                    || $activity->timelineable_type === \VentureDrake\LaravelCrm\Models\Task::class 
                                    || (method_exists($activity->timelineable, 'getMorphClass') && $activity->timelineable_type === (new \VentureDrake\LaravelCrm\Models\Task)->getMorphClass())
                                    || ($activity->recordable && method_exists($activity->recordable, 'noteable') && $activity->recordable->noteable instanceof \VentureDrake\LaravelCrm\Models\Task));
                                $title = ($isTaskActivity && $activityType === 'note')
                                    ? ($userName ? $userName . ' added a note to task' : 'Added a note to task')
                                    : (($userName ? $userName . ' created a ' : 'Created a ') . ($activityType ?? 'activity'));
                            @endphp

                            <x-crm-timeline-item
                                :title="$title"
                                :subtitle="$activity->created_at->format('M d, Y h:i A') . ' (' . $activity->created_at->diffForHumans() . ')'"
                                :activity="$activity"
                                :activityType="$activityType"
                                :first="$loop->first"
                                :last="$loop->last"
                            />
                        @empty
                            <div class="p-8 text-center text-gray-500">
                                <x-mary-icon name="o-calendar" class="w-10 h-10 text-base-content/30 mx-auto mb-2" />
                                <div class="font-bold text-base text-base-content/70">No Activities Found</div>
                                <div class="text-xs text-base-content/50 mt-1">Try clearing or adjusting your activity filters.</div>
                            </div>
                        @endforelse
                    </x-mary-card>

                    <div class="mt-4">
                        {{ $this->activities->links() }}
                    </div>
                @endif
            </x-mary-tab>
        @endforeach
    </x-mary-tabs>
</div>
