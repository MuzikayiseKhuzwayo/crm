<div class="crm-content">
    <x-mary-header title="{{ ucfirst(__('laravel-crm::lang.linkedin_outreach')) }}" subtitle="Control Center & Campaigns" progress-indicator>
        <x-slot:middle class="justify-end!">
            <x-mary-input placeholder="{{ ucfirst(__('laravel-crm::lang.linkedin_campaigns')) }}..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-mary-select wire:model.live="status" :options="$statuses" placeholder="{{ ucfirst(__('laravel-crm::lang.status')) }}" placeholder-value="" />
            <x-mary-button label="Connect Extension" wire:click="$set('showTokenModal', true)" icon="o-key" class="btn-outline" responsive />
            <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.templates')) }}" link="{{ route('laravel-crm.linkedin-templates.index') }}" icon="o-document-text" class="btn-outline" responsive />
            @can('create', \VentureDrake\LaravelCrm\Models\LinkedinCampaign::class)
                <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.create')) }}" link="{{ route('laravel-crm.linkedin-campaigns.create') }}" icon="o-plus" class="btn-primary text-white" responsive />
            @endcan
        </x-slot:actions>
    </x-mary-header>

    {{-- Top Telemetry Bar --}}
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
        <div class="stats shadow bg-base-100 border border-base-200">
            <div class="stat">
                <div class="stat-title flex items-center justify-between">
                    <span>Local Worker Status</span>
                    @if($telemetry['worker_online'])
                        <span class="badge badge-success badge-sm text-white">Online</span>
                    @else
                        <span class="badge badge-neutral badge-sm text-white">Offline</span>
                    @endif
                </div>
                <div class="stat-value text-xl mt-1">
                    {{ $telemetry['worker_online'] ? 'Connected' : 'Disconnected' }}
                </div>
                <div class="stat-desc">
                    @if($telemetry['last_heartbeat_at'])
                        Last heartbeat {{ $telemetry['last_heartbeat_at']->diffForHumans() }}
                    @else
                        No heartbeat received yet
                    @endif
                </div>
            </div>
        </div>

        <x-mary-stat
            title="Today's Connects"
            value="{{ $telemetry['connects_today'] }} / {{ $telemetry['daily_connect_limit'] }}"
            description="Daily safety cap"
            icon="o-user-plus"
            class="border border-base-200"
        />

        <x-mary-stat
            title="Today's DMs"
            value="{{ $telemetry['dms_today'] }} / {{ $telemetry['daily_dm_limit'] }}"
            description="Daily messaging cap"
            icon="o-chat-bubble-left-right"
            class="border border-base-200"
        />

        <x-mary-stat
            title="Due Tasks"
            value="{{ $telemetry['due_tasks_count'] }}"
            description="Ready for extension lease"
            icon="o-queue-list"
            class="border border-base-200"
        />
    </div>

    {{-- Circuit Breaker Alert Banner --}}
    @if($telemetry['circuit_broken'])
        <div role="alert" class="alert alert-error mb-5 shadow">
            <x-mary-icon name="o-shield-exclamation" class="w-6 h-6 shrink-0" />
            <div class="flex-1">
                <h3 class="font-bold">Circuit Breaker Tripped ({{ $telemetry['circuit_broken_count'] }} {{ \Illuminate\Support\Str::plural('campaign', $telemetry['circuit_broken_count']) }})</h3>
                <div class="text-xs">
                    Reason: <span class="font-mono font-semibold">{{ $telemetry['circuit_broken_reason'] ?? 'unknown' }}</span>
                    @if($telemetry['circuit_broken_at'])
                        · Tripped {{ $telemetry['circuit_broken_at']->diffForHumans() }}
                    @endif
                    — All active LinkedIn outreach has been automatically halted to protect your account.
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-mary-button
                    label="Reset (Keep Paused)"
                    wire:click="resetCircuitBreaker(false)"
                    class="btn-sm btn-ghost border border-current"
                    spinner="resetCircuitBreaker"
                />
                <x-mary-button
                    label="Reset & Resume"
                    wire:click="resetCircuitBreaker(true)"
                    icon="o-play"
                    class="btn-sm btn-neutral text-white"
                    spinner="resetCircuitBreaker"
                />
            </div>
        </div>
    @endif

    {{-- Campaigns Table --}}
    <x-mary-card shadow>
        <x-mary-table :headers="$headers" :rows="$campaigns" with-pagination :sort-by="$sortBy" class="whitespace-nowrap">
            @scope('cell_name', $campaign)
                <a href="{{ route('laravel-crm.linkedin-campaigns.show', $campaign) }}" class="link link-hover font-medium">
                    {{ $campaign->name }}
                </a>
                <div class="text-xs text-base-content/50">
                    {{ count($campaign->sequence_steps ?? []) }} {{ \Illuminate\Support\Str::plural('step', count($campaign->sequence_steps ?? [])) }}
                    · Cap {{ $campaign->daily_connect_limit }}/d connects
                </div>
            @endscope

            @scope('cell_status', $campaign)
                @php
                    $statusBadge = match ($campaign->status) {
                        \VentureDrake\LaravelCrm\Models\LinkedinCampaign::STATUS_ACTIVE => 'badge-success text-white',
                        \VentureDrake\LaravelCrm\Models\LinkedinCampaign::STATUS_PAUSED => 'badge-warning',
                        \VentureDrake\LaravelCrm\Models\LinkedinCampaign::STATUS_CIRCUIT_BROKEN => 'badge-error text-white',
                        \VentureDrake\LaravelCrm\Models\LinkedinCampaign::STATUS_COMPLETED => 'badge-info text-white',
                        default => 'badge-neutral text-white',
                    };
                @endphp
                <x-mary-badge :value="ucfirst(str_replace('_', ' ', $campaign->status))" :class="$statusBadge" />
            @endscope

            @scope('cell_total_recipients', $campaign)
                <span class="tabular-nums font-medium">{{ $campaign->total_recipients }}</span>
            @endscope

            @scope('cell_connects_sent_count', $campaign)
                <span class="tabular-nums">{{ $campaign->connects_sent_count }}</span>
            @endscope

            @scope('cell_accepted_count', $campaign)
                <span class="tabular-nums font-medium">{{ $campaign->acceptanceRate() }}%</span>
                <span class="text-xs text-base-content/60 tabular-nums">({{ $campaign->accepted_count }})</span>
            @endscope

            @scope('cell_dms_sent_count', $campaign)
                <span class="tabular-nums">{{ $campaign->dms_sent_count }}</span>
            @endscope

            @scope('cell_replied_count', $campaign)
                <span class="tabular-nums font-medium">{{ $campaign->replyRate() }}%</span>
                <span class="text-xs text-base-content/60 tabular-nums">({{ $campaign->replied_count }})</span>
            @endscope

            @scope('actions', $campaign)
                <div class="flex gap-1 justify-end items-center">
                    @can('update', $campaign)
                        @if(! $campaign->isCircuitBroken() && $campaign->status !== \VentureDrake\LaravelCrm\Models\LinkedinCampaign::STATUS_COMPLETED)
                            <x-mary-button
                                wire:click="toggleStatus({{ $campaign->id }})"
                                :label="$campaign->isActive() ? 'Pause' : 'Activate'"
                                :icon="$campaign->isActive() ? 'o-pause' : 'o-play'"
                                :class="$campaign->isActive() ? 'btn-sm btn-warning' : 'btn-sm btn-success text-white'"
                                spinner="toggleStatus({{ $campaign->id }})"
                            />
                        @endif
                    @endcan
                    @can('view', $campaign)
                        <x-mary-button icon="o-eye" link="{{ route('laravel-crm.linkedin-campaigns.show', $campaign) }}" class="btn-sm btn-square btn-outline" tooltip="Open Campaign Builder" />
                    @endcan
                    @can('update', $campaign)
                        <x-mary-button icon="o-pencil-square" link="{{ route('laravel-crm.linkedin-campaigns.edit', $campaign) }}" class="btn-sm btn-square btn-outline" tooltip="{{ ucfirst(__('laravel-crm::lang.edit')) }}" />
                    @endcan
                    @can('delete', $campaign)
                        <x-mary-button wire:click="delete({{ $campaign->id }})" wire:confirm="Delete this LinkedIn campaign?" icon="o-trash" class="btn-sm btn-square btn-error text-white" spinner tooltip="{{ ucfirst(__('laravel-crm::lang.delete')) }}" />
                    @endcan
                </div>
            @endscope
        </x-mary-table>
    </x-mary-card>

    {{-- API Token Quick-Copy Modal --}}
    <x-mary-modal wire:model="showTokenModal" title="Connect LinkedIn Chrome Extension" subtitle="10-second Sanctum API setup" box-class="max-w-2xl">
        @include('laravel-crm::livewire.linkedin-campaigns._api-token-panel')
        <x-slot:actions>
            <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.close')) }}" wire:click="$set('showTokenModal', false)" />
        </x-slot:actions>
    </x-mary-modal>
</div>
