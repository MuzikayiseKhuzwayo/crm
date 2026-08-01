<x-crm::app-layout title="{{ ucfirst(__('laravel-crm::lang.quotes')) }}">
    <livewire:crm-quote-board layout="board" :sortable="auth()->user()?->can('edit crm quotes') ?? false" />
</x-crm::app-layout>
