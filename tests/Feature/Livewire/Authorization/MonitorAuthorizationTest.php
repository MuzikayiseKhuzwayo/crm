<?php

use Livewire\Livewire;
use VentureDrake\LaravelCrm\Livewire\Monitors\MonitorCreate;
use VentureDrake\LaravelCrm\Livewire\Monitors\MonitorEdit;
use VentureDrake\LaravelCrm\Models\Monitor;

/**
 * Render-stub subclasses -- see NoteAuthorizationTest for the rationale. Only render()
 * is replaced; every guarded action method runs for real against the real MonitorPolicy.
 */
class AuthzMonitorCreate extends MonitorCreate
{
    public function render()
    {
        return '<div></div>';
    }
}
class AuthzMonitorEdit extends MonitorEdit
{
    public function render()
    {
        return '<div></div>';
    }
}

function authzMonitor(): Monitor
{
    return Monitor::create([
        // rules() requires user_owner_id, and MonitorEdit::mount() seeds it from the
        // record -- leaving it null makes save() fail validation rather than the guard.
        'user_owner_id' => auth()->id(),
        'name' => 'Original monitor',
        'type' => 'https',
        'url' => 'https://original.example.test',
        'method' => 'GET',
        'expected_status_code' => 200,
        'interval' => 5,
        'downtime_minutes_before_alert' => 5,
        'perf_threshold_ms' => 3500,
        'is_active' => true,
    ]);
}

/*
 * ---------------------------------------------------------------------------
 * MonitorCreate::save
 * ---------------------------------------------------------------------------
 */

it('forbids creating a monitor without the create permission and stores nothing', function () {
    $this->actingAsUserWithPermissions(['view crm monitors']);
    $before = Monitor::count();

    Livewire::test(AuthzMonitorCreate::class)
        ->set('name', 'Denied monitor')
        ->set('url', 'https://denied.example.test')
        ->call('save')
        ->assertForbidden();

    expect(Monitor::count())->toBe($before)
        ->and(Monitor::where('name', 'Denied monitor')->exists())->toBeFalse();
});

it('creates a monitor with the create permission', function () {
    $this->actingAsUserWithPermissions(['view crm monitors', 'create crm monitors']);

    Livewire::test(AuthzMonitorCreate::class)
        ->set('name', 'Allowed monitor')
        ->set('url', 'https://allowed.example.test')
        ->call('save')
        ->assertOk();

    expect(Monitor::where('name', 'Allowed monitor')->exists())->toBeTrue();
});

/*
 * ---------------------------------------------------------------------------
 * MonitorEdit::save
 * ---------------------------------------------------------------------------
 */

it('forbids updating a monitor without the edit permission and leaves the record intact', function () {
    $this->actingAsUserWithPermissions(['view crm monitors']);
    $monitor = authzMonitor();

    Livewire::test(AuthzMonitorEdit::class, ['monitor' => $monitor])
        ->set('name', 'Tampered')
        ->set('url', 'https://tampered.example.test')
        ->call('save')
        ->assertForbidden();

    expect($monitor->fresh()->name)->toBe('Original monitor')
        ->and($monitor->fresh()->url)->toBe('https://original.example.test');
});

it('updates a monitor with the edit permission', function () {
    $this->actingAsUserWithPermissions(['view crm monitors', 'edit crm monitors']);
    $monitor = authzMonitor();

    Livewire::test(AuthzMonitorEdit::class, ['monitor' => $monitor])
        ->set('name', 'Renamed monitor')
        ->call('save')
        ->assertOk();

    expect($monitor->fresh()->name)->toBe('Renamed monitor');
});
