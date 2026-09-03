<div class="crm-content">
    {{-- HEADER --}}
    <x-mary-header title="{{ ucfirst(__('laravel-crm::lang.tasks')) }}" progress-indicator>
        {{-- SEARCH --}}
        <x-slot:middle class="justify-end!">
            <x-mary-input placeholder="{{ ucfirst(__('laravel-crm::lang.search_tasks')) }}..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
        </x-slot:middle>

        {{-- ACTIONS --}}
        <x-slot:actions>
            <x-mary-button label="Filters"
                           icon="o-funnel"
                           :badge="$filterCount ?? 0"
                           badge-classes="font-mono badge-primary badge-soft"
                           @click="$wire.showFilters = true"
                           responsive />

            @can('create crm tasks')
                <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.create_task')) }}" link="{{ url(route('laravel-crm.tasks.create')) }}" icon="o-plus" class="btn-primary text-white" responsive />
            @endcan
        </x-slot:actions>
    </x-mary-header>

    {{-- ACTIVE FILTERS SUMMARY --}}
    @if($filterCount > 0)
        <div class="flex flex-wrap items-center gap-2 mb-4 p-3 bg-base-200/50 rounded-lg text-xs">
            <span class="font-semibold text-base-content/70">Active Filters:</span>
            @if($status)
                <x-mary-badge value="Status: {{ ucfirst($status) }}" class="badge-primary badge-outline gap-1" icon="o-x-mark" wire:click="$set('status', null)" />
            @endif
            @if(!empty($user_id))
                <x-mary-badge value="Assigned: {{ count($user_id) }} selected" class="badge-primary badge-outline gap-1" icon="o-x-mark" wire:click="$set('user_id', [])" />
            @endif
            @if(!empty($created_by_id))
                <x-mary-badge value="Creator: {{ count($created_by_id) }} selected" class="badge-primary badge-outline gap-1" icon="o-x-mark" wire:click="$set('created_by_id', [])" />
            @endif
            @if(!empty($lead_id))
                <x-mary-badge value="Lead: {{ count($lead_id) }} selected" class="badge-primary badge-outline gap-1" icon="o-x-mark" wire:click="$set('lead_id', [])" />
            @endif
            @if($taskable_type)
                <x-mary-badge value="Entity Type Filter" class="badge-primary badge-outline gap-1" icon="o-x-mark" wire:click="$set('taskable_type', null)" />
            @endif
            @if($due_preset)
                <x-mary-badge value="Due: {{ str_replace('_', ' ', ucfirst($due_preset)) }}" class="badge-primary badge-outline gap-1" icon="o-x-mark" wire:click="$set('due_preset', null)" />
            @endif
            @if($due_from || $due_to)
                <x-mary-badge value="Due Range: {{ $due_from }} - {{ $due_to }}" class="badge-primary badge-outline gap-1" icon="o-x-mark" wire:click="$set('due_from', null); $set('due_to', null);" />
            @endif
            @if($created_preset)
                <x-mary-badge value="Created: {{ str_replace('_', ' ', ucfirst($created_preset)) }}" class="badge-primary badge-outline gap-1" icon="o-x-mark" wire:click="$set('created_preset', null)" />
            @endif
            @if($created_from || $created_to)
                <x-mary-badge value="Created Range: {{ $created_from }} - {{ $created_to }}" class="badge-primary badge-outline gap-1" icon="o-x-mark" wire:click="$set('created_from', null); $set('created_to', null);" />
            @endif
            @if($assigned_preset)
                <x-mary-badge value="Assigned Date: {{ str_replace('_', ' ', ucfirst($assigned_preset)) }}" class="badge-primary badge-outline gap-1" icon="o-x-mark" wire:click="$set('assigned_preset', null)" />
            @endif
            @if($assigned_from || $assigned_to)
                <x-mary-badge value="Assigned Range: {{ $assigned_from }} - {{ $assigned_to }}" class="badge-primary badge-outline gap-1" icon="o-x-mark" wire:click="$set('assigned_from', null); $set('assigned_to', null);" />
            @endif

            <button wire:click="clear" class="text-xs text-error hover:underline ml-auto font-medium">Clear All</button>
        </div>
    @endif

    {{-- TABLE --}}
    <x-mary-card shadow>
        <x-mary-table :headers="$headers" :rows="$tasks" :link="route('laravel-crm.tasks.show', ['task' => '[id]'])" with-pagination :sort-by="$sortBy" class="whitespace-nowrap">
            {{-- NAME & DESCRIPTION --}}
            @scope('cell_name', $task)
                <div>
                    <a href="{{ route('laravel-crm.tasks.show', $task) }}" class="font-semibold text-base-content hover:text-primary">
                        {{ $task->name }}
                    </a>
                    @if($task->description)
                        <p class="text-xs text-neutral-content/70 truncate max-w-xs">{{ $task->description }}</p>
                    @endif
                </div>
            @endscope

            {{-- DUE DATE --}}
            @scope('cell_due_at', $task)
                @if($task->due_at)
                    @if(!$task->completed_at && $task->due_at->isPast())
                        <div class="flex items-center gap-1 text-error text-xs font-semibold">
                            <x-mary-icon name="o-exclamation-triangle" class="w-4 h-4" />
                            <span>{{ $task->due_at->format('M d, Y H:i') }}</span>
                            <span class="text-[10px] opacity-80">({{ $task->due_at->diffForHumans() }})</span>
                        </div>
                    @elseif(!$task->completed_at && $task->due_at->isToday())
                        <div class="flex items-center gap-1 text-warning text-xs font-semibold">
                            <x-mary-icon name="o-clock" class="w-4 h-4" />
                            <span>Today {{ $task->due_at->format('H:i') }}</span>
                        </div>
                    @else
                        <span class="text-xs text-base-content/80">{{ $task->due_at->format('M d, Y H:i') }}</span>
                    @endif
                @else
                    <span class="text-xs text-neutral-content/50">-</span>
                @endif
            @endscope

            {{-- CREATED DATE --}}
            @scope('cell_created_at', $task)
                <div class="text-xs">
                    <div>{{ $task->created_at->format('M d, Y') }}</div>
                    <div class="text-[10px] text-neutral-content/70">{{ $task->created_at->diffForHumans() }}</div>
                </div>
            @endscope

            {{-- ASSIGNED TO USER --}}
            @scope('cell_assigned_user_name', $task)
                @if($task->assignedToUser)
                    <div class="flex items-center gap-2">
                        <x-mary-avatar :title="$task->assignedToUser->name" class="w-6 h-6 text-xs bg-primary/10 text-primary font-bold" />
                        <span class="text-xs font-medium">{{ $task->assignedToUser->name }}</span>
                    </div>
                @else
                    <x-mary-badge value="Unassigned" class="badge-sm badge-ghost text-xs" />
                @endif
            @endscope

            {{-- ASSIGNED / UPDATED DATE --}}
            @scope('cell_updated_at', $task)
                <div class="text-xs text-base-content/80">
                    <div>{{ $task->updated_at->format('M d, Y') }}</div>
                    <div class="text-[10px] text-neutral-content/70">{{ $task->updated_at->diffForHumans() }}</div>
                </div>
            @endscope

            {{-- RELATED LEAD / ENTITY --}}
            @scope('cell_lead_title', $task)
                @if($task->taskable)
                    @if($task->taskable_type === 'VentureDrake\LaravelCrm\Models\Lead')
                        <a href="{{ route('laravel-crm.leads.show', $task->taskable) }}" class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline">
                            <x-mary-icon name="o-funnel" class="w-3.5 h-3.5" />
                            <span>{{ $task->taskable->title }}</span>
                        </a>
                    @elseif($task->taskable_type === 'VentureDrake\LaravelCrm\Models\Deal')
                        <a href="{{ route('laravel-crm.deals.show', $task->taskable) }}" class="inline-flex items-center gap-1 text-xs font-medium text-secondary hover:underline">
                            <x-mary-icon name="o-briefcase" class="w-3.5 h-3.5" />
                            <span>{{ $task->taskable->title }}</span>
                        </a>
                    @elseif($task->taskable_type === 'VentureDrake\LaravelCrm\Models\Person')
                        <a href="{{ route('laravel-crm.people.show', $task->taskable) }}" class="inline-flex items-center gap-1 text-xs font-medium text-info hover:underline">
                            <x-mary-icon name="o-user" class="w-3.5 h-3.5" />
                            <span>{{ $task->taskable->name }}</span>
                        </a>
                    @elseif($task->taskable_type === 'VentureDrake\LaravelCrm\Models\Organization')
                        <a href="{{ route('laravel-crm.organizations.show', $task->taskable) }}" class="inline-flex items-center gap-1 text-xs font-medium text-accent hover:underline">
                            <x-mary-icon name="o-building-office" class="w-3.5 h-3.5" />
                            <span>{{ $task->taskable->name }}</span>
                        </a>
                    @else
                        <span class="text-xs text-base-content/70">{{ $task->taskable->title ?? $task->taskable->name }}</span>
                    @endif
                @else
                    <span class="text-xs text-neutral-content/50">-</span>
                @endif
            @endscope

            {{-- STATUS --}}
            @scope('cell_completed_at', $task)
                @if($task->completed_at)
                    <x-mary-badge value="Completed" class="badge-success text-white badge-sm" />
                @elseif($task->due_at && $task->due_at->isPast())
                    <x-mary-badge value="Overdue" class="badge-error text-white badge-sm" />
                @else
                    <x-mary-badge value="Pending" class="badge-neutral badge-sm" />
                @endif
            @endscope

            {{-- ACTIONS --}}
            @scope('actions', $task)
                <div class="flex gap-1 justify-end">
                    @can('edit crm tasks')
                        @if(! $task->completed_at)
                            <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.complete')) }}" wire:click="complete({{ $task->id }})" class="btn-sm btn-success text-white" spinner />
                        @endif
                    @endcan
                    @can('view crm tasks')
                        <x-mary-button icon="o-eye" link="{{ url(route('laravel-crm.tasks.show', $task)) }}" class="btn-sm btn-square btn-outline" />
                    @endcan    
                    @can('edit crm tasks')
                        <x-mary-button icon="o-pencil-square" link="{{ url(route('laravel-crm.tasks.edit', $task)) }}" class="btn-sm btn-square btn-outline" />
                    @endcan
                    @can('delete crm tasks')
                        <x-mary-button onclick="modalDeleteTask{{ $task->id }}.showModal()" icon="o-trash" class="btn-sm btn-square btn-error text-white" spinner />
                        <x-crm-delete-confirm model="task" id="{{ $task->id }}" />
                    @endcan
                </div>
            @endscope
        </x-mary-table>
    </x-mary-card>

    {{-- FILTERS DRAWER --}}
    <x-mary-drawer wire:model="showFilters" title="Filter & Order Tasks" class="lg:w-1/3" right separator with-close-button>
        <div class="grid gap-5" @keydown.enter="$wire.showFilters = false">
            {{-- STATUS & ENTITY TYPE --}}
            <div class="space-y-4">
                <h4 class="font-semibold text-sm text-base-content/80">Status & Entity</h4>
                <x-mary-select label="Task Status" wire:model.live="status" :options="[
                    ['id' => '', 'name' => 'All Statuses'],
                    ['id' => 'pending', 'name' => ucfirst(__('laravel-crm::lang.pending'))],
                    ['id' => 'completed', 'name' => ucfirst(__('laravel-crm::lang.completed'))],
                    ['id' => 'overdue', 'name' => 'Overdue'],
                ]" icon="o-check-circle" />

                <x-mary-select label="Related Entity Type" wire:model.live="taskable_type" :options="$entityTypeOptions" icon="o-shapes" />
            </div>

            {{-- ASSIGNMENT & CREATOR --}}
            <div class="space-y-4 pt-3 border-t border-base-200">
                <h4 class="font-semibold text-sm text-base-content/80">People & Lead</h4>
                <x-mary-choices label="{{ ucfirst(__('laravel-crm::lang.assigned_to')) }}" wire:model.live="user_id" :options="$userOptions" icon="o-user" allow-all />
                <x-mary-choices label="{{ ucfirst(__('laravel-crm::lang.created_by')) }}" wire:model.live="created_by_id" :options="$users" icon="o-user-circle" allow-all />
                <x-mary-choices label="Filter by Lead" wire:model.live="lead_id" :options="$leads" option-label="title" icon="o-funnel" allow-all />
            </div>

            {{-- DUE DATE --}}
            <div class="space-y-4 pt-3 border-t border-base-200">
                <h4 class="font-semibold text-sm text-base-content/80">Due Date</h4>
                <x-mary-select label="Due Date Presets" wire:model.live="due_preset" :options="$duePresets" icon="o-calendar" />
                <div class="grid grid-cols-2 gap-2">
                    <x-mary-input label="Due From" type="date" wire:model.live="due_from" />
                    <x-mary-input label="Due To" type="date" wire:model.live="due_to" />
                </div>
            </div>

            {{-- CREATED DATE --}}
            <div class="space-y-4 pt-3 border-t border-base-200">
                <h4 class="font-semibold text-sm text-base-content/80">Created Date</h4>
                <x-mary-select label="Created Date Presets" wire:model.live="created_preset" :options="$createdPresets" icon="o-clock" />
                <div class="grid grid-cols-2 gap-2">
                    <x-mary-input label="Created From" type="date" wire:model.live="created_from" />
                    <x-mary-input label="Created To" type="date" wire:model.live="created_to" />
                </div>
            </div>

            {{-- ASSIGNED DATE --}}
            <div class="space-y-4 pt-3 border-t border-base-200">
                <h4 class="font-semibold text-sm text-base-content/80">Assigned / Updated Date</h4>
                <x-mary-select label="Assigned Date Presets" wire:model.live="assigned_preset" :options="$assignedPresets" icon="o-calendar-days" />
                <div class="grid grid-cols-2 gap-2">
                    <x-mary-input label="Assigned From" type="date" wire:model.live="assigned_from" />
                    <x-mary-input label="Assigned To" type="date" wire:model.live="assigned_to" />
                </div>
            </div>
        </div>

        {{-- ACTIONS --}}
        <x-slot:actions>
            <x-mary-button label="Reset Filters" icon="o-x-mark" wire:click="clear" spinner />
            <x-mary-button label="Apply & Close" icon="o-check" class="btn-primary text-white" @click="$wire.showFilters = false" />
        </x-slot:actions>
    </x-mary-drawer>
</div>
