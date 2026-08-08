<?php

namespace VentureDrake\LaravelCrm\Livewire\Monitors;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use VentureDrake\LaravelCrm\Livewire\Monitors\Traits\HasMonitorCommon;
use VentureDrake\LaravelCrm\Models\Monitor;

class MonitorCreate extends Component
{
    use AuthorizesRequests, HasMonitorCommon;

    public function mount(): void
    {
        $this->user_owner_id = auth()->user()->id ?? null;
    }

    public function save()
    {
        $this->authorize('create', Monitor::class);

        $validated = $this->validate();

        try {
            $monitor = $this->monitorService->create($validated);
        } catch (\Throwable $e) {
            report($e);
            $this->error(ucfirst(__('laravel-crm::lang.monitor')).' '.__('laravel-crm::lang.could_not_be_saved').': '.$e->getMessage());

            return;
        }

        $this->success(
            ucfirst(__('laravel-crm::lang.monitor')).' '.__('laravel-crm::lang.stored'),
            redirectTo: route('laravel-crm.monitors.show', $monitor)
        );
    }

    public function render()
    {
        return view('laravel-crm::livewire.monitors.monitor-create');
    }
}
