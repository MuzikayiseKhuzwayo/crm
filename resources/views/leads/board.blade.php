<x-crm::app-layout title="{{ ucfirst(__('laravel-crm::lang.leads')) }}">
    <livewire:crm-lead-board layout="board" :sortable="auth()->user()?->can('edit crm leads') ?? false" />
</x-crm::app-layout>
