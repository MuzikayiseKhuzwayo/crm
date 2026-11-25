<div class="crm-content">
    <x-mary-header title="{{ ucfirst(__('laravel-crm::lang.edit')) }} {{ $campaign->name }}">
        <x-slot:actions>
            <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.back')) }}" link="{{ route('laravel-crm.linkedin-campaigns.show', $campaign) }}" />
        </x-slot:actions>
    </x-mary-header>

    <x-mary-form wire:submit="save">
        <x-mary-card shadow title="Campaign Details">
            <x-mary-input wire:model="name" label="{{ ucfirst(__('laravel-crm::lang.name')) }}" required />
        </x-mary-card>

        <x-mary-card shadow class="mt-5">
            @include('laravel-crm::livewire.linkedin-campaigns._sequence-editor')
        </x-mary-card>

        <x-mary-card shadow class="mt-5" title="Safety Caps & Schedule">
            @include('laravel-crm::livewire.linkedin-campaigns._safety-schedule-form')
        </x-mary-card>

        <x-slot:actions>
            <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.cancel')) }}" link="{{ route('laravel-crm.linkedin-campaigns.show', $campaign) }}" />
            <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.save')) }}" class="btn-primary text-white" type="submit" spinner="save" />
        </x-slot:actions>
    </x-mary-form>
</div>
