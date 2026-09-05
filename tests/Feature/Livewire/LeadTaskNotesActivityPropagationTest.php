<?php

use Livewire\Livewire;
use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Livewire\Activities\ActivityIndex;
use VentureDrake\LaravelCrm\Livewire\Notes\NoteRelated;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Models\Task;
use VentureDrake\LaravelCrm\Tests\Stubs\User;

it('propagates task notes and activities upwards to lead details page with direct task links', function () {
    $user = User::create(['name' => 'Sales Director', 'email' => 'salesdirector@example.com']);
    $this->actingAs($user);

    $person = Person::create([
        'external_id' => Uuid::uuid4()->toString(),
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);

    $lead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Big Enterprise Deal',
        'person_id' => $person->id,
    ]);

    $task = Task::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Negotiate licensing terms',
        'description' => 'Follow up on contract amendments',
        'taskable_type' => get_class($lead),
        'taskable_id' => $lead->id,
        'due_at' => now()->addDays(2),
        'user_owner_id' => $user->id,
        'user_assigned_id' => $user->id,
    ]);

    // Create note on task as done in NoteRelated::save()
    $note = $task->notes()->create([
        'external_id' => Uuid::uuid4()->toString(),
        'content' => 'Client accepted 5-seat tier, requested redline draft.',
        'created_by' => $user->id,
        'pinned' => 1,
    ]);

    // Activity created on the task
    $task->activities()->create([
        'causeable_type' => $user->getMorphClass(),
        'causeable_id' => $user->id,
        'timelineable_type' => $task->getMorphClass(),
        'timelineable_id' => $task->id,
        'recordable_type' => $note->getMorphClass(),
        'recordable_id' => $note->id,
    ]);

    // 1. Verify ActivityIndex mounted on $lead includes the task note activity and renders task link
    $activityTest = Livewire::test(ActivityIndex::class, ['model' => $lead]);
    $activityTest->assertSee('added a note to task')
        ->assertSee('Negotiate licensing terms')
        ->assertSee(route('laravel-crm.tasks.show', $task))
        ->assertSee('Client accepted 5-seat tier, requested redline draft.');

    // 2. Verify NoteRelated mounted on $lead includes the inner note created on the task
    $noteTest = Livewire::test(NoteRelated::class, ['model' => $lead]);
    $noteTest->assertSee('Negotiate licensing terms')
        ->assertSee(route('laravel-crm.tasks.show', $task))
        ->assertSee('Client accepted 5-seat tier, requested redline draft.');

    // 3. Verify pinned task notes also surface in pinned NoteRelated on the lead
    $pinnedNoteTest = Livewire::test(NoteRelated::class, ['model' => $lead, 'pinned' => true]);
    $pinnedNoteTest->assertSee('Negotiate licensing terms')
        ->assertSee(route('laravel-crm.tasks.show', $task))
        ->assertSee('Client accepted 5-seat tier, requested redline draft.');
});
