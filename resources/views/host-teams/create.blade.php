<x-crm::app-layout title="{{ __('Create team') }}">
    <div class="crm-content">
        <x-mary-header title="{{ __('Create team') }}" class="mb-5" />

        <div class="max-w-xl">
            <x-mary-card>
                <form method="POST" action="{{ route('laravel-crm.host-teams.store') }}" class="space-y-4">
                    @csrf

                    <x-mary-input
                        label="{{ __('Name') }}"
                        name="name"
                        value="{{ old('name') }}"
                        required
                        autofocus
                    />

                    @error('name')
                        <p class="text-error text-sm">{{ $message }}</p>
                    @enderror

                    <div class="flex items-center gap-3 pt-2">
                        <x-mary-button
                            label="{{ __('Cancel') }}"
                            link="{{ url(route('laravel-crm.dashboard')) }}"
                            class="btn"
                        />
                        <x-mary-button
                            label="{{ __('Create team') }}"
                            class="btn-primary text-white"
                            type="submit"
                        />
                    </div>
                </form>
            </x-mary-card>
        </div>
    </div>
</x-crm::app-layout>
