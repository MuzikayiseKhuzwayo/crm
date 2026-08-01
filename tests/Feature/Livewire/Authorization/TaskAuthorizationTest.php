<?php

use Livewire\Livewire;
use VentureDrake\LaravelCrm\Livewire\Tasks\TaskCreate;
use VentureDrake\LaravelCrm\Livewire\Tasks\TaskIndex;
use VentureDrake\LaravelCrm\Livewire\Tasks\TaskShow;
use VentureDrake\LaravelCrm\Models\Task;

/**
 * Render-stub subclasses.
 *
 * Livewire renders a component on mount, and the CRM index/show/edit blades reach for
 * activity + contact tables the minimal TestSchema does not ship. Overriding only
 * render() leaves the real action methods -- and the $this->authorize() guards inside
 * them -- completely intact, so these tests still exercise the production
 * authorization path against the real policies.
 */
class AuthzTaskIndex extends TaskIndex
{
    public function render()
    {
        return '<div></div>';
    }
}
class AuthzTaskCreate extends TaskCreate
{
    public function render()
    {
        return '<div></div>';
    }
}
class AuthzTaskShow extends TaskShow
{
    public function render()
    {
        return '<div></div>';
    }
}

it('forbids deleting a task without the delete permission and leaves the record intact', function () {
    $this->actingAsUserWithPermissions(['view crm tasks']);
    $record = Task::create(['name' => 'Authz task']);

    Livewire::test(AuthzTaskIndex::class)
        ->call('delete', $record->id)
        ->assertForbidden();

    expect(Task::find($record->id))->not->toBeNull();
});

it('allows deleting a task with the delete permission', function () {
    $this->actingAsUserWithPermissions(['view crm tasks', 'delete crm tasks']);
    $record = Task::create(['name' => 'Authz task']);

    Livewire::test(AuthzTaskIndex::class)
        ->call('delete', $record->id)
        ->assertOk();

    expect(Task::find($record->id))->toBeNull();
});

it('forbids creating a task without the create permission', function () {
    $this->actingAsUserWithPermissions(['view crm tasks']);

    Livewire::test(AuthzTaskCreate::class)
        ->call('save')
        ->assertForbidden();
});

it('allows a user holding the create permission past the task create guard', function () {
    $this->actingAsUserWithPermissions(['view crm tasks', 'create crm tasks']);

    // Not forbidden: the guard passes and execution reaches validation, which is what
    // proves a seeded role that can create has not lost access.
    Livewire::test(AuthzTaskCreate::class)
        ->call('save')
        ->assertOk();
});

it('forbids completing a task without the edit permission', function () {
    $this->actingAsUserWithPermissions(['view crm tasks']);
    $record = Task::create(['name' => 'Authz task']);

    Livewire::test(AuthzTaskShow::class, ['task' => $record])
        ->call('complete')
        ->assertForbidden();

    expect($record->fresh()->completed_at)->toBeNull();
});

it('allows completing a task with the edit permission', function () {
    $this->actingAsUserWithPermissions(['view crm tasks', 'edit crm tasks']);
    $record = Task::create(['name' => 'Authz task']);

    Livewire::test(AuthzTaskShow::class, ['task' => $record])
        ->call('complete')
        ->assertOk();

    expect($record->fresh()->completed_at)->not->toBeNull();
});
