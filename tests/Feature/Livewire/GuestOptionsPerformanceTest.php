<?php

use Livewire\Livewire;
use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Livewire\Calls\CallItem;
use VentureDrake\LaravelCrm\Livewire\Calls\CallRelated;
use VentureDrake\LaravelCrm\Livewire\Lunches\LunchRelated;
use VentureDrake\LaravelCrm\Livewire\Meetings\MeetingRelated;
use VentureDrake\LaravelCrm\Livewire\Tasks\TaskShow;
use VentureDrake\LaravelCrm\Models\Call;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Models\Task;
use VentureDrake\LaravelCrm\Tests\Stubs\User;

it('scopes guest options and does not load unbounded person records', function () {
    $user = User::create(['name' => 'Test User', 'email' => 'testuser@example.com']);
    $this->actingAs($user);

    // Seed 40 random people
    for ($i = 1; $i <= 40; $i++) {
        Person::create([
            'external_id' => Uuid::uuid4()->toString(),
            'first_name' => "Person{$i}",
            'last_name' => "Test{$i}",
            'user_owner_id' => $user->id,
        ]);
    }

    $specificPerson = Person::create([
        'external_id' => Uuid::uuid4()->toString(),
        'first_name' => 'Target',
        'last_name' => 'Contact',
        'user_owner_id' => $user->id,
    ]);

    $lead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Important Lead',
        'person_id' => $specificPerson->id,
        'user_owner_id' => $user->id,
    ]);

    $task = Task::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Review lead note',
        'taskable_type' => Lead::class,
        'taskable_id' => $lead->id,
        'user_owner_id' => $user->id,
    ]);

    // Render CallRelated with Task
    Livewire::test(CallRelated::class, ['model' => $task])
        ->assertViewHas('persons', function ($persons) use ($specificPerson) {
            $collection = collect($persons);
            expect($collection->count())->toBeLessThanOrEqual(25)
                ->and($collection->contains('id', $specificPerson->id))->toBeTrue();

            return true;
        });

    // Render MeetingRelated with Task
    Livewire::test(MeetingRelated::class, ['model' => $task])
        ->assertViewHas('persons', function ($persons) use ($specificPerson) {
            $collection = collect($persons);
            expect($collection->count())->toBeLessThanOrEqual(25)
                ->and($collection->contains('id', $specificPerson->id))->toBeTrue();

            return true;
        });

    // Render LunchRelated with Task
    Livewire::test(LunchRelated::class, ['model' => $task])
        ->assertViewHas('persons', function ($persons) use ($specificPerson) {
            $collection = collect($persons);
            expect($collection->count())->toBeLessThanOrEqual(25)
                ->and($collection->contains('id', $specificPerson->id))->toBeTrue();

            return true;
        });

    // Item components should pass empty persons when not editing
    $call = Call::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Follow up call',
        'callable_type' => Task::class,
        'callable_id' => $task->id,
        'user_owner_id' => $user->id,
    ]);

    Livewire::test(CallItem::class, ['call' => $call])
        ->assertViewHas('persons', function ($persons) {
            return empty($persons);
        });

    // Render full TaskShow component
    Livewire::test(TaskShow::class, ['task' => $task])
        ->assertSee('Review lead note')
        ->assertSee('Important Lead');
});
