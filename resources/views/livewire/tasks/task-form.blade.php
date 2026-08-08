<div>
    <x-mary-card title="{{ ucfirst(__('laravel-crm::lang.details')) }}" separator>
        <div class="grid gap-3">
            <x-mary-input wire:model="name" label="{{ ucfirst(__('laravel-crm::lang.name')) }}" />
            <x-mary-textarea wire:model="description" label="{{ ucfirst(__('laravel-crm::lang.description')) }}" rows="4" />
            @include('laravel-crm::livewire.tasks.partials.schedule-fields', ['startLabel' => 'start_at', 'dueLabel' => 'due_date'])
            <x-mary-select label="{{ ucfirst(__('laravel-crm::lang.owner')) }}" wire:model="user_owner_id" :options="\VentureDrake\LaravelCrm\Http\Helpers\SelectOptions\usersOptions(false)" />
            <x-mary-select label="{{ ucfirst(__('laravel-crm::lang.assigned_to')) }}" wire:model="user_assigned_id" :options="\VentureDrake\LaravelCrm\Http\Helpers\SelectOptions\usersOptions(false)" />
        </div>
    </x-mary-card>
</div>

