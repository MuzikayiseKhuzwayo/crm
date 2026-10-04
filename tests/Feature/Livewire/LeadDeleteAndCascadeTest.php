<?php

use Livewire\Livewire;
use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Livewire\Leads\LeadShow;
use VentureDrake\LaravelCrm\Models\Activity;
use VentureDrake\LaravelCrm\Models\Call;
use VentureDrake\LaravelCrm\Models\Deal;
use VentureDrake\LaravelCrm\Models\Email;
use VentureDrake\LaravelCrm\Models\File;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Lunch;
use VentureDrake\LaravelCrm\Models\Meeting;
use VentureDrake\LaravelCrm\Models\Note;
use VentureDrake\LaravelCrm\Models\Order;
use VentureDrake\LaravelCrm\Models\Phone;
use VentureDrake\LaravelCrm\Models\Quote;
use VentureDrake\LaravelCrm\Models\Task;

it('allows deleting lead from LeadShow component and cascades soft delete to related tasks and activities', function () {
    $user = $this->actingAsUser();

    $lead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Test Lead To Delete',
        'user_owner_id' => $user->id,
    ]);

    // Attach related task
    $task = Task::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Lead Followup Task',
        'taskable_type' => get_class($lead),
        'taskable_id' => $lead->id,
        'user_owner_id' => $user->id,
    ]);

    // Attach related note
    $note = Note::create([
        'external_id' => Uuid::uuid4()->toString(),
        'content' => 'Lead note content',
        'noteable_type' => get_class($lead),
        'noteable_id' => $lead->id,
    ]);

    // Attach related call, meeting, lunch, file
    $call = Call::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Call with client',
        'callable_type' => get_class($lead),
        'callable_id' => $lead->id,
    ]);
    $meeting = Meeting::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Strategy Meeting',
        'meetingable_type' => get_class($lead),
        'meetingable_id' => $lead->id,
    ]);
    $lunch = Lunch::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Client Lunch',
        'lunchable_type' => get_class($lead),
        'lunchable_id' => $lead->id,
    ]);
    $file = File::create([
        'external_id' => Uuid::uuid4()->toString(),
        'fileable_type' => get_class($lead),
        'fileable_id' => $lead->id,
        'file' => 'test.pdf',
        'filesize' => 1024,
    ]);

    // Attach activity
    $activity = Activity::create([
        'external_id' => Uuid::uuid4()->toString(),
        'timelineable_type' => get_class($lead),
        'timelineable_id' => $lead->id,
    ]);

    // Attach direct phone and email
    $phone = $lead->phones()->create([
        'external_id' => Uuid::uuid4()->toString(),
        'number' => '+1234567890',
        'primary' => 1,
    ]);
    $email = $lead->emails()->create([
        'external_id' => Uuid::uuid4()->toString(),
        'address' => 'lead@example.com',
        'primary' => 1,
    ]);

    // Attach deal, quote, order referencing this lead
    $deal = Deal::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Deal from Lead',
        'lead_id' => $lead->id,
        'user_owner_id' => $user->id,
    ]);
    $quote = Quote::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Quote from Lead',
        'lead_id' => $lead->id,
        'user_owner_id' => $user->id,
    ]);
    $order = Order::create([
        'external_id' => Uuid::uuid4()->toString(),
        'lead_id' => $lead->id,
        'user_owner_id' => $user->id,
    ]);

    // Test calling delete on LeadShow
    Livewire::test(LeadShow::class, ['lead' => $lead])
        ->call('delete', $lead->id)
        ->assertRedirect(route('laravel-crm.leads.index'));

    // Verify lead is soft-deleted
    expect(Lead::find($lead->id))->toBeNull()
        ->and(Lead::withTrashed()->find($lead->id))->not->toBeNull();

    // Verify related task is soft-deleted
    expect(Task::find($task->id))->toBeNull()
        ->and(Task::withTrashed()->find($task->id)->deleted_at)->not->toBeNull();

    // Verify related note, call, meeting, lunch, file, activity are soft-deleted
    expect(Note::find($note->id))->toBeNull()
        ->and(Call::find($call->id))->toBeNull()
        ->and(Meeting::find($meeting->id))->toBeNull()
        ->and(Lunch::find($lunch->id))->toBeNull()
        ->and(File::find($file->id))->toBeNull()
        ->and(Activity::find($activity->id))->toBeNull();

    // Verify direct phone and email are soft-deleted
    expect(Phone::find($phone->id))->toBeNull()
        ->and(Email::find($email->id))->toBeNull();

    // Verify foreign references were nullified
    expect($deal->fresh()->lead_id)->toBeNull()
        ->and($quote->fresh()->lead_id)->toBeNull()
        ->and($order->fresh()->lead_id)->toBeNull();

    // Test restoring the lead
    $lead->restore();

    // Verify lead and its tasks/notes/activities are restored
    expect(Lead::find($lead->id))->not->toBeNull();
    expect(Task::find($task->id))->not->toBeNull();
    expect(Note::find($note->id))->not->toBeNull();
    expect(Call::find($call->id))->not->toBeNull();
    expect(Meeting::find($meeting->id))->not->toBeNull();
    expect(Lunch::find($lunch->id))->not->toBeNull();
    expect(File::find($file->id))->not->toBeNull();
    expect(Activity::find($activity->id))->not->toBeNull();
    expect(Phone::find($phone->id))->not->toBeNull();
    expect(Email::find($email->id))->not->toBeNull();
});
