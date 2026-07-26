<div class="crm-content">
    {{-- HEADER --}}
    <x-mary-header title="{{ ucfirst(__('laravel-crm::lang.invite_user')) }}" class="mb-5" progress-indicator>

        {{-- ACTIONS --}}
        <x-slot:actions>
            <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.back_to_users')) }}" link="{{ url(route('laravel-crm.users.index')) }}" icon="fas.angle-double-left" class="btn-sm" responsive />
        </x-slot:actions>
    </x-mary-header>

    <x-mary-form wire:submit="save">
        <x-mary-card title="{{ ucfirst(__('laravel-crm::lang.details')) }}" separator>
            <div class="grid gap-3">
                <x-mary-input wire:model="email" label="{{ ucfirst(__('laravel-crm::lang.email')) }}" type="email" />
                <x-mary-select wire:model="role_id" :options="$roles" label="{{ ucfirst(__('laravel-crm::lang.CRM_role')) }}" />
            </div>
        </x-mary-card>

        <x-slot:actions>
            <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.cancel')) }}" class="btn" link="{{ url(route('laravel-crm.users.index')) }}" />
            <x-mary-button label="{{ ucfirst(__('laravel-crm::lang.send_invite')) }}" class="btn-primary text-white" type="submit" spinner="save" />
        </x-slot:actions>
    </x-mary-form>
</div>
