<div class="crm-content">
    <x-mary-header title="{{ ucfirst(__('laravel-crm::lang.linkedin_templates')) }}" progress-indicator>
        <x-slot:middle class="justify-end!">
            <x-mary-input placeholder="{{ ucfirst(__('laravel-crm::lang.linkedin_templates')) }}..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-mary-select wire:model.live="type" :options="$types" placeholder="{{ ucfirst(__('laravel-crm::lang.type')) }}" placeholder-value="" />
            <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.linkedin_campaigns')) }}" link="{{ route('laravel-crm.linkedin-campaigns.index') }}" icon="o-rocket-launch" class="btn-outline" responsive />
            @can('create', \VentureDrake\LaravelCrm\Models\LinkedinTemplate::class)
                <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.create')) }}" link="{{ route('laravel-crm.linkedin-templates.create') }}" icon="o-plus" class="btn-primary text-white" responsive />
            @endcan
        </x-slot:actions>
    </x-mary-header>

    <x-mary-card shadow>
        <x-mary-table :headers="$headers" :rows="$templates" with-pagination :sort-by="$sortBy">
            @scope('cell_type', $template)
                @php
                    $badgeClass = match ($template->type) {
                        \VentureDrake\LaravelCrm\Models\LinkedinTemplate::TYPE_CONNECT_NOTE => 'badge-info text-white',
                        \VentureDrake\LaravelCrm\Models\LinkedinTemplate::TYPE_DM => 'badge-primary text-white',
                        \VentureDrake\LaravelCrm\Models\LinkedinTemplate::TYPE_FOLLOW_UP => 'badge-secondary text-white',
                        default => 'badge-neutral text-white',
                    };
                    $typeLabel = match ($template->type) {
                        \VentureDrake\LaravelCrm\Models\LinkedinTemplate::TYPE_CONNECT_NOTE => ucfirst(__('laravel-crm::lang.linkedin_template_type_connect_note')),
                        \VentureDrake\LaravelCrm\Models\LinkedinTemplate::TYPE_DM => ucfirst(__('laravel-crm::lang.linkedin_template_type_dm')),
                        \VentureDrake\LaravelCrm\Models\LinkedinTemplate::TYPE_FOLLOW_UP => ucfirst(__('laravel-crm::lang.linkedin_template_type_follow_up')),
                        default => ucfirst($template->type),
                    };
                @endphp
                <x-mary-badge :value="$typeLabel" :class="$badgeClass" />
            @endscope
            @scope('cell_body', $template)
                <div class="max-w-md truncate text-sm text-base-content/80" title="{{ $template->body }}">
                    {{ $template->body }}
                </div>
                <div class="text-xs tabular-nums {{ $template->type === 'connect_note' && mb_strlen($template->body ?? '') > 300 ? 'text-error font-semibold' : 'text-base-content/50' }}">
                    {{ mb_strlen($template->body ?? '') }} {{ __('laravel-crm::lang.characters') }}
                    @if($template->type === 'connect_note')
                        / 300
                    @endif
                </div>
            @endscope
            @scope('cell_created_at', $template)
                {{ $template->created_at?->diffForHumans() }}
            @endscope
            @scope('actions', $template)
                <div class="flex gap-1 justify-end">
                    @can('update', $template)
                        <x-mary-button icon="o-pencil-square" link="{{ route('laravel-crm.linkedin-templates.edit', $template) }}" class="btn-sm btn-square btn-outline" tooltip="{{ ucfirst(__('laravel-crm::lang.edit')) }}" />
                    @endcan
                    @can('create', \VentureDrake\LaravelCrm\Models\LinkedinTemplate::class)
                        <x-mary-button icon="o-document-duplicate" link="{{ route('laravel-crm.linkedin-templates.create', ['clone_from' => $template->id]) }}" class="btn-sm btn-square btn-outline" tooltip="{{ ucfirst(__('laravel-crm::lang.clone')) }}" />
                    @endcan
                    @can('delete', $template)
                        <x-mary-button wire:click="delete({{ $template->id }})" wire:confirm="Delete this LinkedIn template?" icon="o-trash" class="btn-sm btn-square btn-error text-white" spinner tooltip="{{ ucfirst(__('laravel-crm::lang.delete')) }}" />
                    @endcan
                </div>
            @endscope
        </x-mary-table>
    </x-mary-card>
</div>
