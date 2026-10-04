<?php

use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Organization;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Models\Task;
use VentureDrake\LaravelCrm\Tests\Stubs\User;

it('syncs leads, contacts, organizations and tasks from sqlite database', function () {
    $user = User::create(['name' => 'Sync Officer', 'email' => 'sync@example.com']);
    $this->actingAs($user);

    $this->artisan('laravelcrm:setup-lead-pipeline')->assertExitCode(0);

    // Create a temporary SQLite database
    $tempSqlite = tempnam(sys_get_temp_dir(), 'crm_sync_test_').'.sqlite';
    $sqlite = new PDO("sqlite:{$tempSqlite}");
    $sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create schema in temp sqlite
    $sqlite->exec('
        CREATE TABLE crm_organizations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            external_id TEXT,
            name TEXT,
            linkedin TEXT,
            user_owner_id INTEGER,
            user_created_id INTEGER
        );
        CREATE TABLE crm_people (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            external_id TEXT,
            first_name TEXT,
            last_name TEXT,
            description TEXT,
            organization_id INTEGER,
            linkedin TEXT,
            user_owner_id INTEGER,
            user_created_id INTEGER
        );
        CREATE TABLE crm_addresses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            external_id TEXT,
            addressable_type TEXT,
            addressable_id INTEGER,
            line1 TEXT,
            city TEXT,
            state TEXT,
            country TEXT,
            "primary" INTEGER
        );
        CREATE TABLE crm_leads (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            external_id TEXT,
            person_id INTEGER,
            organization_id INTEGER,
            title TEXT,
            description TEXT,
            lead_source_id INTEGER,
            lead_status_id INTEGER,
            user_owner_id INTEGER,
            user_created_id INTEGER,
            linkedin TEXT,
            pipeline_id INTEGER,
            pipeline_stage_id INTEGER,
            currency TEXT,
            created_at TEXT,
            updated_at TEXT
        );
        CREATE TABLE crm_tasks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            external_id TEXT,
            name TEXT,
            description TEXT,
            taskable_type TEXT,
            taskable_id INTEGER,
            due_at TEXT,
            completed_at TEXT,
            user_owner_id INTEGER,
            user_assigned_id INTEGER,
            created_at TEXT,
            updated_at TEXT
        );
    ');

    // Seed dummy records into temp sqlite
    $sqlite->exec("
        INSERT INTO crm_organizations (id, external_id, name, linkedin) VALUES
        (10, 'org-ext-1', 'Stark Industries', 'https://linkedin.com/company/stark-ind');

        INSERT INTO crm_people (id, external_id, first_name, last_name, organization_id, linkedin) VALUES
        (20, 'person-ext-1', 'Tony', 'Stark', 10, 'https://linkedin.com/in/tony-stark-unique');

        INSERT INTO crm_addresses (id, external_id, addressable_type, addressable_id, city, country, \"primary\") VALUES
        (30, 'addr-ext-1', 'VentureDrake\\\\LaravelCrm\\\\Models\\\\Person', 20, 'Malibu', 'United States', 1);

        INSERT INTO crm_leads (id, external_id, person_id, organization_id, title, linkedin, pipeline_stage_id) VALUES
        (40, 'lead-ext-1', 20, 10, 'Stark Industries - Arc Reactor', 'https://linkedin.com/in/tony-stark-unique', 1);

        INSERT INTO crm_tasks (id, external_id, name, taskable_type, taskable_id, completed_at) VALUES
        (50, 'task-ext-1', 'Send an introductory DM', 'VentureDrake\\\\LaravelCrm\\\\Models\\\\Lead', 40, '2026-09-30 12:00:00');
    ");

    // Dry-run
    $this->artisan('laravelcrm:sync-leads-from-sqlite', [
        '--sqlite-path' => $tempSqlite,
        '--dry-run' => true,
        '--user' => $user->id,
    ])->assertExitCode(0);

    expect(Lead::where('linkedin', 'https://linkedin.com/in/tony-stark-unique')->exists())->toBeFalse();

    // Actual Sync
    $this->artisan('laravelcrm:sync-leads-from-sqlite', [
        '--sqlite-path' => $tempSqlite,
        '--user' => $user->id,
    ])->assertExitCode(0);

    // Verify Organization was synced
    $org = Organization::where('name', 'Stark Industries')->first();
    expect($org)->not->toBeNull()
        ->and($org->linkedin)->toBe('https://linkedin.com/company/stark-ind');

    // Verify Person was synced
    $person = Person::where('linkedin', 'https://linkedin.com/in/tony-stark-unique')->first();
    expect($person)->not->toBeNull()
        ->and($person->first_name)->toBe('Tony')
        ->and($person->last_name)->toBe('Stark')
        ->and($person->organization_id)->toBe($org->id);

    // Verify Lead was synced
    $lead = Lead::where('linkedin', 'https://linkedin.com/in/tony-stark-unique')->first();
    expect($lead)->not->toBeNull()
        ->and($lead->title)->toBe('Stark Industries - Arc Reactor')
        ->and($lead->person_id)->toBe($person->id)
        ->and($lead->organization_id)->toBe($org->id);

    // Verify Task was synced and attached to lead
    $task = Task::where('name', 'Send an introductory DM')->first();
    expect($task)->not->toBeNull()
        ->and($task->taskable_id)->toBe($lead->id)
        ->and($task->completed_at)->not->toBeNull();

    // Clean up
    @unlink($tempSqlite);
});
