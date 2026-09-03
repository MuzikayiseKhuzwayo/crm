<?php

use Livewire\Livewire;
use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Livewire\Activities\ActivityFeed;
use VentureDrake\LaravelCrm\Models\Activity;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Note;
use VentureDrake\LaravelCrm\Tests\Stubs\User;
use VentureDrake\LaravelCrm\View\Components\TimelineItem;

it('resolves origin entity, author user, and assigned user on TimelineItem', function () {
    $user = User::create(['name' => 'Agent Performer', 'email' => 'performer@example.com']);
    $this->actingAs($user);

    $lead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'High Alpha Strategy Lead',
        'user_owner_id' => $user->id,
        'user_created_id' => $user->id,
    ]);

    $note = Note::create([
        'external_id' => Uuid::uuid4()->toString(),
        'content' => 'Discussed initial risk parameters and order routing.',
        'user_created_id' => $user->id,
    ]);

    $activity = Activity::create([
        'external_id' => Uuid::uuid4()->toString(),
        'causeable_type' => User::class,
        'causeable_id' => $user->id,
        'timelineable_type' => Lead::class,
        'timelineable_id' => $lead->id,
        'recordable_type' => Note::class,
        'recordable_id' => $note->id,
    ]);

    $component = new TimelineItem(
        title: 'User created a note',
        activity: $activity,
        activityType: 'note'
    );

    $origin = $component->getOriginEntity();
    $author = $component->getAuthorUser();

    expect($origin)->not()->toBeNull();
    expect($origin->title)->toBe('High Alpha Strategy Lead');
    expect($author->name)->toBe('Agent Performer');
});

it('filters activity feed by origin entity type and user', function () {
    $user1 = User::create(['name' => 'User One', 'email' => 'user1@example.com']);
    $user2 = User::create(['name' => 'User Two', 'email' => 'user2@example.com']);
    $this->actingAs($user1);

    $lead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Lead One',
        'user_owner_id' => $user1->id,
    ]);

    $note = Note::create([
        'external_id' => Uuid::uuid4()->toString(),
        'content' => 'Note 1 content',
        'user_created_id' => $user1->id,
    ]);

    Activity::create([
        'external_id' => Uuid::uuid4()->toString(),
        'causeable_type' => User::class,
        'causeable_id' => $user1->id,
        'timelineable_type' => Lead::class,
        'timelineable_id' => $lead->id,
        'recordable_type' => Note::class,
        'recordable_id' => $note->id,
    ]);

    Livewire::test(ActivityFeed::class)
        ->set('entityType', Lead::class)
        ->assertSet('entityType', Lead::class)
        ->set('user_id', $user1->id)
        ->assertSet('user_id', $user1->id);
});
