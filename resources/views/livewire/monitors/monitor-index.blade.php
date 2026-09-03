<div class="crm-content">
    <x-mary-header title="{{ ucfirst(__('laravel-crm::lang.monitors')) }}" progress-indicator>
        <x-slot:middle class="justify-end!">
            <x-mary-input placeholder="{{ ucfirst(__('laravel-crm::lang.monitors')) }}..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-mary-select wire:model.live="status" :options="$statuses" placeholder="{{ ucfirst(__('laravel-crm::lang.status')) }}" placeholder-value="" />
            @can('create crm monitors')
                <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.create')) }}" link="{{ route('laravel-crm.monitors.create') }}" icon="o-plus" class="btn-primary text-white" responsive />
            @endcan
        </x-slot:actions>
    </x-mary-header>

    {{-- EXECUTIVE MONITORING STATS --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-base-100 p-4 shadow border border-base-200 rounded-xl flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold text-base-content/60">System Status</p>
                <x-mary-badge :value="$metrics['system_status']" class="{{ $metrics['system_color'] }} text-white font-bold mt-1" />
            </div>
            <x-mary-icon name="o-signal" class="w-7 h-7 text-primary opacity-80" />
        </div>

        <x-mary-stat title="Monitored Endpoints"
                    :value="$metrics['total']"
                    icon="o-globe-alt"
                    class="bg-base-100 shadow border border-base-200 rounded-xl" />

        <x-mary-stat title="Avg Response Time"
                    :value="$metrics['avg_response'].' ms'"
                    icon="o-bolt"
                    class="bg-base-100 shadow border border-base-200 rounded-xl" />

        <x-mary-stat title="SSL Expiry Alerts"
                    :value="$metrics['ssl_warning']"
                    icon="o-shield-check"
                    class="bg-base-100 shadow border border-base-200 rounded-xl" />
    </div>

    {{-- TABLE --}}
    <x-mary-card shadow>
        <x-mary-table :headers="$headers" :rows="$monitors" :link="route('laravel-crm.monitors.show', ['monitor' => '[id]'])" with-pagination :sort-by="$sortBy" class="whitespace-nowrap">
            @scope('cell_monitor_id', $monitor)
                <span class="font-mono text-xs font-bold text-primary">{{ $monitor->monitor_id }}</span>
            @endscope

            @scope('cell_name', $monitor)
                <div>
                    <a href="{{ route('laravel-crm.monitors.show', $monitor) }}" class="font-bold text-xs text-base-content hover:text-primary hover:underline">
                        {{ $monitor->displayName() }}
                    </a>
                    <div class="text-[11px] font-mono text-neutral-content/60">{{ $monitor->url }}</div>
                </div>
            @endscope

            @scope('cell_performance', $monitor)
                @php
                    $bars = (array) ($monitor->performance_bars ?? array_fill(0, 7, 0));
                    $max = max($bars) ?: 1;
                    $width = 100;
                    $height = 28;
                    $gap = 2;
                    $barWidth = ($width - ($gap * 6)) / 7;
                @endphp
                <svg viewBox="0 0 {{ $width }} {{ $height }}" width="{{ $width }}" height="{{ $height }}" aria-hidden="true" style="display:inline-block;vertical-align:middle">
                    @foreach($bars as $i => $value)
                        @php
                            $h = $value > 0 ? max(2, ($value / $max) * $height) : 1;
                            $x = $i * ($barWidth + $gap);
                            $y = $height - $h;
                        @endphp
                        <rect x="{{ $x }}" y="{{ $y }}" width="{{ $barWidth }}" height="{{ $h }}" rx="1" fill="#05b3a9" />
                    @endforeach
                </svg>
            @endscope

            @scope('cell_last_status', $monitor)
                @php
                    $statusClass = match($monitor->last_status) {
                        'up' => 'badge-success',
                        'down' => 'badge-error',
                        'slow' => 'badge-warning',
                        default => 'badge-neutral',
                    };
                @endphp
                <x-mary-badge :value="ucfirst($monitor->last_status ?? '—')" class="{{ $statusClass }} text-white font-bold" />
            @endscope

            @scope('cell_last_response_time', $monitor)
                <span class="font-mono text-xs font-semibold">
                    {{ $monitor->last_response_time !== null ? $monitor->last_response_time.' ms' : '—' }}
                </span>
            @endscope

            @scope('cell_last_checked_at', $monitor)
                <span class="text-xs text-base-content/70">
                    {{ $monitor->last_checked_at?->diffForHumans() ?? '—' }}
                </span>
            @endscope

            @scope('actions', $monitor)
                <div class="flex gap-1 justify-end">
                    <x-mary-button icon="o-arrow-path"
                                   wire:click="checkNow({{ $monitor->id }})"
                                   spinner
                                   title="Check endpoint now"
                                   class="btn-sm btn-square btn-outline btn-primary" />

                    @can('view crm monitors')
                        <x-mary-button icon="o-eye" link="{{ route('laravel-crm.monitors.show', $monitor) }}" class="btn-sm btn-square btn-outline" />
                    @endcan
                    @can('edit crm monitors')
                        <x-mary-button icon="o-pencil-square" link="{{ route('laravel-crm.monitors.edit', $monitor) }}" class="btn-sm btn-square btn-outline" />
                    @endcan
                    @can('delete crm monitors')
                        <x-mary-button onclick="modalDeleteMonitor{{ $monitor->id }}.showModal()" icon="o-trash" class="btn-sm btn-square btn-error text-white" />
                        <x-crm-delete-confirm model="monitor" id="{{ $monitor->id }}" deleting="monitor" />
                    @endcan
                </div>
            @endscope
        </x-mary-table>
    </x-mary-card>
</div>
