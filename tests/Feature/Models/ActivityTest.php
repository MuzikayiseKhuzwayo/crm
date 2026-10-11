<?php

use Illuminate\Support\Facades\DB;
use VentureDrake\LaravelCrm\Models\Activity;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Tests\Stubs\User;

test('activity uses prefixed table', function () {
    expect((new Activity)->getTable())->toBe('crm_activities');
});

test('activity can be created for a lead', function () {
    $lead = Lead::create(['title' => 'L']);

    $activity = Activity::create([
        'log_name' => 'leads', 'description' => 'Lead created',
        'event' => 'created', 'recordable_type' => Lead::class, 'recordable_id' => $lead->id,
    ]);

    expect($activity->log_name)->toBe('leads');
    expect($activity->event)->toBe('created');
});

test('activity causeable safely resolves when causeable_type is missing or empty in database', function () {
    $user = User::first() ?? User::create([
        'name' => 'Test User',
        'email' => 'test_user_activity@example.com',
        'password' => bcrypt('password'),
    ]);

    $activity = Activity::create([
        'log_name' => 'leads',
        'description' => 'Test missing causeable_type',
        'causeable_type' => null,
        'causeable_id' => $user->id,
    ]);

    // Force DB column to null to simulate legacy or corrupted data
    DB::table((new Activity)->getTable())
        ->where('id', $activity->id)
        ->update(['causeable_type' => null, 'causeable_id' => $user->id]);

    $freshActivity = Activity::find($activity->id);
    expect($freshActivity->causeable)->not->toBeNull()
        ->and($freshActivity->causeable->id)->toBe($user->id);
});

test('activity timelineable and recordable do not execute invalid query when type is missing', function () {
    $activity = Activity::create([
        'log_name' => 'leads',
        'description' => 'Test empty morph type',
    ]);

    DB::table((new Activity)->getTable())
        ->where('id', $activity->id)
        ->update(['timelineable_type' => null, 'timelineable_id' => 999]);

    $fresh = Activity::find($activity->id);
    expect($fresh->timelineable)->toBeNull();
});
