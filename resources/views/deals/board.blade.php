<x-crm::app-layout title="{{ ucfirst(__('laravel-crm::lang.deals')) }}">
    <livewire:crm-deal-board layout="board" :sortable="auth()->user()?->can('edit crm deals') ?? false" />
</x-crm::app-layout>
