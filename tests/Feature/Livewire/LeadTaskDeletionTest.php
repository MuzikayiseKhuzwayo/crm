<?php

use Livewire\Livewire;
use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Livewire\Activities\ActivityIndex;
use VentureDrake\LaravelCrm\Livewire\ActivityTabs;
use VentureDrake\LaravelCrm\Livewire\Tasks\TaskItem;
use VentureDrake\LaravelCrm\Livewire\Tasks\TaskRelated;
use VentureDrake\LaravelCrm\Models\Activity;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Task;

it('renders unique delete modal IDs for the same task across Activity and Tasks tabs on a lead page', function () {
    $user = $this->actingAsUserWithPermissions([
        'view crm leads',
        'view crm tasks',
        'edit crm tasks',
        'delete crm tasks',
    ]);

    $lead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Acme Quant - Head of Research',
    ]);

    $task = $lead->tasks()->create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Send LinkedIn connection request',
        'description' => 'Initial outreach message',
        'user_owner_id' => $user->id,
        'user_assigned_id' => $user->id,
    ]);

    $lead->activities()->create([
        'causeable_type' => $user->getMorphClass(),
        'causeable_id' => $user->id,
        'timelineable_type' => $lead->getMorphClass(),
        'timelineable_id' => $lead->id,
        'recordable_type' => $task->getMorphClass(),
        'recordable_id' => $task->id,
    ]);

    $html = Livewire::test(ActivityTabs::class, ['model' => $lead])
        ->assertOk()
        ->html();

    preg_match_all('/<dialog id="(modalDeleteTaskItem-'.$task->id.'-[^"]+)"/', $html, $dialogMatches);
    preg_match_all('/document\.getElementById\(\'(modalDeleteTaskItem-'.$task->id.'-[^\']+)\'\)\?\.showModal\(\)/', $html, $triggerMatches);

    // Mounted in both ActivityIndex (Activity tab) and TaskRelated (Tasks tab)
    expect($dialogMatches[1])->toHaveCount(2);
    expect(array_unique($dialogMatches[1]))->toHaveCount(2);
    expect($triggerMatches[1])->toEqual($dialogMatches[1]);
});

it('deletes a lead task via TaskItem and removes it from both TaskRelated and ActivityIndex', function () {
    $user = $this->actingAsUserWithPermissions([
        'view crm leads',
        'view crm tasks',
        'delete crm tasks',
    ]);

    $lead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Vector Capital - Portfolio Manager',
    ]);

    $task = $lead->tasks()->create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Follow up on quant pilot',
        'user_owner_id' => $user->id,
        'user_assigned_id' => $user->id,
    ]);

    $activity = $lead->activities()->create([
        'causeable_type' => $user->getMorphClass(),
        'causeable_id' => $user->id,
        'timelineable_type' => $lead->getMorphClass(),
        'timelineable_id' => $lead->id,
        'recordable_type' => $task->getMorphClass(),
        'recordable_id' => $task->id,
    ]);

    Livewire::test(TaskItem::class, ['task' => $task])
        ->call('delete')
        ->assertOk()
        ->assertDispatched('task-updated')
        ->assertDispatched('activity-logged');

    expect(Task::find($task->id))->toBeNull();
    expect(Activity::find($activity->id))->toBeNull();

    Livewire::test(TaskRelated::class, ['model' => $lead])
        ->assertOk()
        ->assertDontSee('Follow up on quant pilot');

    Livewire::test(ActivityIndex::class, ['model' => $lead])
        ->assertOk()
        ->assertDontSee('Follow up on quant pilot');
});

it('keeps the delete confirmation dialog mounted while a TaskItem is in edit mode', function () {
    $user = $this->actingAsUserWithPermissions([
        'view crm tasks',
        'edit crm tasks',
        'delete crm tasks',
    ]);

    $task = Task::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Editable task',
        'user_owner_id' => $user->id,
        'user_assigned_id' => $user->id,
    ]);

    $component = Livewire::test(TaskItem::class, ['task' => $task])
        ->call('edit')
        ->assertSet('editing', true);

    $html = $component->html();
    $expectedId = 'modalDeleteTaskItem-'.$task->id.'-'.$component->id();

    expect($html)->toContain('id="'.$expectedId.'"');
    expect($html)->toContain("document.getElementById('{$expectedId}')?.showModal()");
});

it('forbids deleting a task from TaskItem without delete crm tasks permission', function () {
    $user = $this->actingAsUserWithPermissions([
        'view crm tasks',
        'edit crm tasks',
    ]);

    $task = Task::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Protected lead task',
        'user_owner_id' => $user->id,
        'user_assigned_id' => $user->id,
    ]);

    Livewire::test(TaskItem::class, ['task' => $task])
        ->call('delete')
        ->assertForbidden();

    expect(Task::find($task->id))->not->toBeNull();
});
