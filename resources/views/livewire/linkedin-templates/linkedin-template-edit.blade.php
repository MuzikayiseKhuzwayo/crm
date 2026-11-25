<div class="crm-content">
    <x-mary-header title="{{ ucfirst(__('laravel-crm::lang.edit')) }} {{ __('laravel-crm::lang.linkedin_template') }}">
        <x-slot:actions>
            <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.back')) }}" link="{{ route('laravel-crm.linkedin-templates.index') }}" />
        </x-slot:actions>
    </x-mary-header>

    <x-mary-form wire:submit="save">
        <x-mary-card shadow>
            @include('laravel-crm::livewire.linkedin-templates._form')
        </x-mary-card>

        <x-slot:actions>
            <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.cancel')) }}" link="{{ route('laravel-crm.linkedin-templates.index') }}" />
            <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.save')) }}" class="btn-primary text-white" type="submit" spinner="save" />
        </x-slot:actions>
    </x-mary-form>
</div>
