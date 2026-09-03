<div class="crm-content">
    {{-- HEADER --}}
    <x-mary-header title="{{ ucfirst(__('laravel-crm::lang.leads')) }}" progress-indicator>
        {{-- SEARCH --}}
        <x-slot:middle class="justify-end!">
            <x-mary-input placeholder="{{ ucfirst(__('laravel-crm::lang.search_leads')) ?? 'Search leads...' }}..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
        </x-slot:middle>

        {{-- ACTIONS --}}
        <x-slot:actions>
            <x-mary-button label="Filters"
                           icon="o-funnel"
                           :badge="$filterCount ?? 0"
                           badge-classes="font-mono badge-primary badge-soft"
                           @click="$wire.showFilters = true"
                           responsive />

            <x-crm-index-toggle :layout="$layout" model="leads"/>
            @can('create crm leads')
                <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.create_lead')) }}" link="{{ url(route('laravel-crm.leads.create')) }}" icon="o-plus" class="btn-primary text-white" responsive />
            @endcan
        </x-slot:actions>
    </x-mary-header>

    {{-- ACTIVE FILTERS SUMMARY PILLS BAR --}}
    @if($filterCount > 0)
        <div class="flex flex-wrap items-center gap-2 mb-4 p-3 bg-base-200/60 rounded-xl text-xs">
            <span class="font-bold text-base-content/70 flex items-center gap-1">
                <x-mary-icon name="o-funnel" class="w-3.5 h-3.5 text-primary shrink-0" style="width:14px;height:14px;" />
                Active Filters:
            </span>

            @if($lead_status !== 'active')
                <x-mary-badge value="Status: {{ ucfirst($lead_status) }}" icon-right="o-x-mark" wire:click="$set('lead_status', 'active')" class="badge-neutral text-white gap-1 cursor-pointer" />
            @endif

            @if(!empty($user_id))
                <x-mary-badge value="Owners: {{ count((array)$user_id) }}" icon-right="o-x-mark" wire:click="$set('user_id', [])" class="badge-primary text-white gap-1 cursor-pointer" />
            @endif

            @if(!empty($pipeline_stage_id))
                <x-mary-badge value="Stages: {{ count((array)$pipeline_stage_id) }}" icon-right="o-x-mark" wire:click="$set('pipeline_stage_id', [])" class="badge-secondary text-white gap-1 cursor-pointer" />
            @endif

            @if(!empty($lead_source_id))
                <x-mary-badge value="Sources: {{ count((array)$lead_source_id) }}" icon-right="o-x-mark" wire:click="$set('lead_source_id', [])" class="badge-info text-white gap-1 cursor-pointer" />
            @endif

            @if(!empty($label_id))
                <x-mary-badge value="Labels: {{ count((array)$label_id) }}" icon-right="o-x-mark" wire:click="$set('label_id', [])" class="badge-accent text-white gap-1 cursor-pointer" />
            @endif

            @if($amount_preset || $min_amount || $max_amount)
                <x-mary-badge value="Value Filter Active" icon-right="o-x-mark" wire:click="$set('amount_preset', ''); $set('min_amount', null); $set('max_amount', null);" class="badge-success text-white gap-1 cursor-pointer" />
            @endif

            @if($created_preset || $created_from || $created_to)
                <x-mary-badge value="Created Date Filter Active" icon-right="o-x-mark" wire:click="$set('created_preset', ''); $set('created_from', null); $set('created_to', null);" class="badge-warning text-white gap-1 cursor-pointer" />
            @endif

            <button wire:click="clear" class="btn btn-xs btn-ghost text-error font-semibold hover:underline ms-auto">
                Clear All
            </button>
        </div>
    @endif

    {{-- TABLE --}}
    <x-mary-card shadow>
        <x-mary-table :headers="$headers" :rows="$leads" :link="route('laravel-crm.leads.show', ['lead' => '[id]'])" with-pagination :sort-by="$sortBy" class="whitespace-nowrap">
            @scope('cell_lead_id', $lead)
                <span class="font-mono text-xs font-bold text-primary">{{ $lead->lead_id }}</span>
            @endscope

            @scope('cell_title', $lead)
                <a href="{{ route('laravel-crm.leads.show', $lead) }}" class="font-bold text-xs text-base-content hover:text-primary hover:underline">
                    {{ $lead->title }}
                </a>
            @endscope

            @scope('cell_labels', $lead)
                <div class="flex flex-wrap gap-1">
                    @foreach($lead->labels as $label)
                        <x-mary-badge :value="$label->name" class="text-white text-[10px]" :style="'border-color: #'.$label->hex.'; background-color: #'.$label->hex" />
                    @endforeach
                </div>
            @endscope

            @scope('cell_person_name', $lead)
                @if($lead->person)
                    <a href="{{ route('laravel-crm.people.show', $lead->person) }}" class="inline-flex items-center gap-1 text-xs text-info hover:underline">
                        <x-mary-icon name="o-user" class="w-3.5 h-3.5 shrink-0" style="width:14px;height:14px;" />
                        <span>{{ $lead->person->name }}</span>
                    </a>
                @else
                    <span class="text-xs text-neutral-content/40">-</span>
                @endif
            @endscope

            @scope('cell_organization_name', $lead)
                @if($lead->organization)
                    <a href="{{ route('laravel-crm.organizations.show', $lead->organization) }}" class="inline-flex items-center gap-1 text-xs text-accent hover:underline">
                        <x-mary-icon name="o-building-office" class="w-3.5 h-3.5 shrink-0" style="width:14px;height:14px;" />
                        <span>{{ $lead->organization->name }}</span>
                    </a>
                @else
                    <span class="text-xs text-neutral-content/40">-</span>
                @endif
            @endscope

            @scope('cell_pipeline_stage', $lead)
                @if($lead->pipelineStage)
                    <x-mary-badge :value="$lead->pipelineStage->name" class="badge badge-neutral text-white text-[11px]" />
                @else
                    <span class="text-xs text-neutral-content/40">-</span>
                @endif
            @endscope

            @scope('actions', $lead)
                <div class="flex gap-1 justify-end">
                    @can('edit crm leads')
                        <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.convert')) }}" link="{{ route('laravel-crm.deals.create', ['model' => 'lead', 'id' => $lead->id]) }}" class="btn-sm btn-success text-white" />
                    @endcan
                    @can('view crm leads')
                        <x-mary-button icon="o-eye" link="{{ url(route('laravel-crm.leads.show', $lead)) }}" class="btn-sm btn-square btn-outline" />
                    @endcan
                    @can('edit crm leads')    
                        <x-mary-button icon="o-pencil-square" link="{{ url(route('laravel-crm.leads.edit', $lead)) }}" class="btn-sm btn-square btn-outline" />
                    @endcan
                    @can('delete crm leads')
                        <x-mary-button onclick="modalDeleteLead{{ $lead->id }}.showModal()" icon="o-trash" class="btn-sm btn-square btn-error text-white" spinner />
                        <x-crm-delete-confirm model="lead" id="{{ $lead->id }}" />
                    @endcan    
                </div>
            @endscope
        </x-mary-table>
    </x-mary-card>

    {{-- FILTERS DRAWER --}}
    <x-mary-drawer wire:model="showFilters" title="Filter Leads" class="lg:w-1/3" right separator with-close-button>
        <div class="grid gap-6" @keydown.enter="$wire.showFilters = false">
            {{-- 1. LEAD STATUS --}}
            <div>
                <label class="block text-xs font-bold text-base-content/80 mb-2">Lead Status:</label>
                <div class="join w-full">
                    <button type="button" wire:click="$set('lead_status', 'active')" class="join-item btn btn-sm flex-1 {{ $lead_status === 'active' ? 'btn-primary text-white' : 'btn-outline' }}">Active</button>
                    <button type="button" wire:click="$set('lead_status', 'converted')" class="join-item btn btn-sm flex-1 {{ $lead_status === 'converted' ? 'btn-primary text-white' : 'btn-outline' }}">Converted</button>
                    <button type="button" wire:click="$set('lead_status', 'all')" class="join-item btn btn-sm flex-1 {{ $lead_status === 'all' ? 'btn-primary text-white' : 'btn-outline' }}">All Leads</button>
                </div>
            </div>

            {{-- 2. PIPELINE STAGE --}}
            <div>
                <x-mary-choices label="Pipeline Stage" wire:model.live="pipeline_stage_id" :options="$pipelineStages" icon="o-chart-bar" allow-all />
            </div>

            {{-- 3. LEAD SOURCE --}}
            <div>
                <x-mary-choices label="{{ ucwords(__('laravel-crm::lang.lead_source')) }}" wire:model.live="lead_source_id" :options="$leadSources" icon="o-funnel" allow-all />
            </div>

            {{-- 4. OWNER --}}
            <div>
                <x-mary-choices label="Lead Owner" wire:model.live="user_id" :options="$userFilterOptions" icon="o-user" allow-all />
            </div>

            {{-- 5. LABEL --}}
            <div>
                <x-mary-choices label="Labels" wire:model.live="label_id" :options="$labels" icon="o-tag" allow-all />
            </div>

            {{-- 6. LEAD VALUE RANGE --}}
            <div class="space-y-3 bg-base-200/50 p-3.5 rounded-xl border border-base-300">
                <label class="block text-xs font-bold text-base-content/80">Lead Value ($):</label>
                <x-mary-select wire:model.live="amount_preset" :options="$amountPresets" icon="o-currency-dollar" inline />
                <div class="grid grid-cols-2 gap-2">
                    <x-mary-input label="Min ($)" wire:model.live.debounce="min_amount" type="number" placeholder="Min" inline />
                    <x-mary-input label="Max ($)" wire:model.live.debounce="max_amount" type="number" placeholder="Max" inline />
                </div>
            </div>

            {{-- 7. CREATED DATE --}}
            <div class="space-y-3 bg-base-200/50 p-3.5 rounded-xl border border-base-300">
                <label class="block text-xs font-bold text-base-content/80">Created Date:</label>
                <x-mary-select wire:model.live="created_preset" :options="$createdPresets" icon="o-calendar" inline />
                <div class="grid grid-cols-2 gap-2">
                    <x-mary-input label="From" wire:model.live="created_from" type="date" inline />
                    <x-mary-input label="To" wire:model.live="created_to" type="date" inline />
                </div>
            </div>
        </div>

        {{-- ACTIONS --}}
        <x-slot:actions>
            <x-mary-button label="Reset All" icon="o-x-mark" wire:click="clear" spinner />
            <x-mary-button label="Apply Filters" icon="o-check" class="btn-primary text-white" @click="$wire.showFilters = false" />
        </x-slot:actions>
    </x-mary-drawer>
</div>
