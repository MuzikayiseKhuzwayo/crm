<div class="crm-content">
    {{-- HEADER --}}
    <x-mary-header title="{{ ucfirst(__('laravel-crm::lang.features')) }}" progress-indicator>
        <x-slot:middle class="justify-end!">
            <x-mary-input placeholder="{{ ucfirst(__('laravel-crm::lang.features')) }}..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
        </x-slot:middle>

        <x-slot:actions>
            <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.filters')) }}"
                           icon="o-funnel"
                           :badge="$filterCount ?? 0"
                           badge-classes="font-mono badge-primary badge-soft"
                           @click="$wire.showFilters = true"
                           responsive />

            <x-mary-button label="Roadmap Board" link="{{ url(route('laravel-crm.features.board')) }}" icon="o-view-columns" class="btn btn-outline" responsive />

            <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.public_board')) }}"
                           link="{{ $this->publicBoardUrl() }}"
                           icon="o-globe-alt"
                           class="btn btn-outline"
                           external
                           responsive />

            @can('create crm features')
                <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.submit_feature')) }}" link="{{ url(route('laravel-crm.features.create')) }}" icon="o-plus" class="btn-primary text-white" responsive />
            @endcan
        </x-slot:actions>
    </x-mary-header>

    {{-- EXECUTIVE STATS CARDS --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <x-mary-stat title="Total Requests"
                    :value="$metrics['total']"
                    icon="o-light-bulb"
                    class="bg-base-100 shadow border border-base-200 rounded-xl" />

        <x-mary-stat title="User Votes"
                    :value="$metrics['votes']"
                    icon="o-hand-thumb-up"
                    class="bg-base-100 shadow border border-base-200 rounded-xl" />

        <x-mary-stat title="In Roadmap"
                    :value="$metrics['in_progress']"
                    icon="o-clock"
                    class="bg-base-100 shadow border border-base-200 rounded-xl" />

        <x-mary-stat title="Completed"
                    :value="$metrics['completed']"
                    icon="o-check-circle"
                    class="bg-base-100 shadow border border-base-200 rounded-xl" />
    </div>

    {{-- TABLE --}}
    <x-mary-card shadow>
        <x-mary-table :headers="$headers" :rows="$features" :link="route('laravel-crm.features.show', ['feature' => '[id]'])" with-pagination :sort-by="$sortBy" class="whitespace-nowrap">
            @scope('cell_feature_id', $feature)
                <span class="font-mono text-xs font-bold text-primary">{{ $feature->feature_id }}</span>
            @endscope

            @scope('cell_title', $feature)
                <a href="{{ route('laravel-crm.features.show', $feature) }}" class="font-bold text-xs text-base-content hover:text-primary hover:underline">
                    {{ $feature->title }}
                </a>
            @endscope

            @scope('cell_status.name', $feature)
                @if($feature->status)
                    <x-mary-badge :value="$feature->status->name" class="text-white text-xs font-semibold" :style="'background-color: '.($feature->status->color ?? '#6c757d')" />
                @else
                    <span class="text-xs text-neutral-content/40">-</span>
                @endif
            @endscope

            @scope('cell_votes_count', $feature)
                <span class="inline-flex items-center gap-1 font-bold text-xs text-primary">
                    <x-mary-icon name="o-hand-thumb-up" class="w-3.5 h-3.5" style="width:14px;height:14px;" />
                    {{ $feature->votes_count ?? 0 }}
                </span>
            @endscope

            @scope('cell_comments_count', $feature)
                <span class="inline-flex items-center gap-1 text-xs text-base-content/70">
                    <x-mary-icon name="o-chat-bubble-left-right" class="w-3.5 h-3.5" style="width:14px;height:14px;" />
                    {{ $feature->comments_count ?? 0 }}
                </span>
            @endscope

            @scope('actions', $feature)
                <div class="flex gap-1 justify-end">
                    @if($feature->is_public)
                        <x-mary-button icon="o-arrow-top-right-on-square"
                                       link="{{ url(route('laravel-crm.portal.features.show', $feature)) }}"
                                       external
                                       title="{{ ucfirst(__('laravel-crm::lang.public')).' '.__('laravel-crm::lang.view') }}"
                                       class="btn-sm btn-square btn-outline" />
                    @endif
                    @can('edit crm features')
                        <x-mary-button icon="o-pencil-square" link="{{ url(route('laravel-crm.features.edit', $feature)) }}" class="btn-sm btn-square btn-outline" />
                    @endcan
                    @can('view crm features')
                        <x-mary-button icon="o-eye" link="{{ url(route('laravel-crm.features.show', $feature)) }}" class="btn-sm btn-square btn-outline" />
                    @endcan
                    @can('delete crm features')
                        <x-mary-button onclick="modalDeleteFeature{{ $feature->id }}.showModal()" icon="o-trash" class="btn-sm btn-square btn-error text-white" spinner />
                        <x-crm-delete-confirm model="feature" id="{{ $feature->id }}" />
                    @endcan
                </div>
            @endscope
        </x-mary-table>
    </x-mary-card>

    {{-- FILTERS --}}
    <x-mary-drawer wire:model="showFilters" title="{{ ucfirst(__('laravel-crm::lang.filters')) }}" class="lg:w-1/3" right separator with-close-button>
        <div class="grid gap-5" @keydown.enter="$wire.showFilters = false">
            <x-mary-choices label="{{ ucfirst(__('laravel-crm::lang.status')) }}" wire:model.live="feature_status_id" :options="$statuses" icon="o-flag" inline allow-all />
            <x-mary-select label="Visibility" wire:model.live="is_public" :options="[
                ['id' => 1, 'name' => 'Public'],
                ['id' => 0, 'name' => 'Private'],
            ]" placeholder="Any" />
        </div>

        <x-slot:actions>
            <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.clear')) }}" icon="o-x-mark" wire:click="clear" spinner />
            <x-mary-button label="Done" icon="o-check" class="btn-primary" @click="$wire.showFilters = false" />
        </x-slot:actions>
    </x-mary-drawer>
</div>
