<x-crm::app-layout>
    <livewire:crm-feature-board layout="board" :sortable="auth()->user()?->can('edit crm features') ?? false" />
</x-crm::app-layout>
