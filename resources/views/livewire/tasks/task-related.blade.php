<div class="grid gap-5">
    @can('create crm tasks')
    <x-mary-card title="{{ ucfirst(__('laravel-crm::lang.add_task')) }}" separator>
        <x-mary-form wire:submit="save">
            <div class="grid gap-3" wire:key="details">
                @if($lead = $this->resolveLead())
                    <div class="p-3 bg-base-200/50 rounded-xl border border-base-300 space-y-2">
                        <div class="flex flex-wrap items-center justify-between gap-1 text-xs">
                            <span class="font-bold uppercase tracking-wider text-base-content/80 flex items-center gap-1.5">
                                <x-mary-icon name="o-sparkles" class="w-4 h-4 text-warning" />
                                Sales Motions Playbook (DUB-REV-SALES-MOTIONS-V2)
                            </span>
                            <span class="text-[11px] text-base-content/60">Pre-fill tokens with {{ $lead->title }}</span>
                        </div>
                        <x-mary-select 
                            wire:model.live="selectedPlaybookTemplate" 
                            :options="$this->playbookOptions" 
                            placeholder="-- Select a pre-filled Playbook template --"
                            icon="o-document-text" 
                        />
                    </div>
                @endif
                <x-mary-input wire:model="name" label="{{ ucfirst(__('laravel-crm::lang.task')) }}" />
                @include('laravel-crm::livewire.tasks.partials.schedule-fields')
                <x-mary-textarea wire:model="description" label="{{ ucfirst(__('laravel-crm::lang.further_details')) }}" rows="5" />
                <x-mary-select label="{{ ucfirst(__('laravel-crm::lang.who_requested_the_task')) }}" wire:model="user_owner_id" :options="$users" />
                <x-mary-select label="{{ ucfirst(__('laravel-crm::lang.who_is_responsible')) }}" wire:model="user_assigned_id" :options="$users" />
            </div>
            <x-slot:actions>
                <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.save')) }}" class="btn-primary text-white" type="submit" spinner="save" />
            </x-slot:actions>
        </x-mary-form>
    </x-mary-card>
    @endcan

    @if(count($tasks) > 0)
        @foreach($tasks as $task)
            @livewire('crm-task-item', ['task' => $task, 'related' => $data[$task->id]['related']], key('task-item-'.$task->id))
        @endforeach
    @endif
</div>

