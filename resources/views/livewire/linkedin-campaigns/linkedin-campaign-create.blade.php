<div class="crm-content">
    <x-mary-header title="{{ ucfirst(__('laravel-crm::lang.create')) }} {{ __('laravel-crm::lang.linkedin_campaign') }}">
        <x-slot:actions>
            <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.back')) }}" link="{{ route('laravel-crm.linkedin-campaigns.index') }}" />
        </x-slot:actions>
    </x-mary-header>

    <x-mary-form wire:submit="save">
        <x-mary-card shadow title="Campaign Details">
            <div class="grid md:grid-cols-2 gap-5">
                <x-mary-input wire:model="name" label="{{ ucfirst(__('laravel-crm::lang.name')) }}" placeholder="e.g. Apify SaaS Founders Q4" required />
                <x-mary-select
                    wire:model="status"
                    label="Initial Status"
                    :options="[
                        ['id' => \VentureDrake\LaravelCrm\Models\LinkedinCampaign::STATUS_DRAFT, 'name' => 'Draft (recommended until leads are enrolled)'],
                        ['id' => \VentureDrake\LaravelCrm\Models\LinkedinCampaign::STATUS_ACTIVE, 'name' => 'Active immediately'],
                    ]"
                />
            </div>
        </x-mary-card>

        <x-mary-card shadow class="mt-5">
            @include('laravel-crm::livewire.linkedin-campaigns._sequence-editor')
        </x-mary-card>

        <x-mary-card shadow class="mt-5" title="Safety Caps & Schedule">
            @include('laravel-crm::livewire.linkedin-campaigns._safety-schedule-form')
        </x-mary-card>

        <x-slot:actions>
            <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.cancel')) }}" link="{{ route('laravel-crm.linkedin-campaigns.index') }}" />
            <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.save')) }}" class="btn-primary text-white" type="submit" spinner="save" />
        </x-slot:actions>
    </x-mary-form>
</div>
