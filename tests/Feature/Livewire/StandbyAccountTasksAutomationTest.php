<?php

use Carbon\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use VentureDrake\LaravelCrm\Livewire\Leads\LeadIndex;
use VentureDrake\LaravelCrm\Livewire\Leads\LeadShow;
use VentureDrake\LaravelCrm\Livewire\Tasks\TaskIndex;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Organization;
use VentureDrake\LaravelCrm\Models\Task;
use VentureDrake\LaravelCrm\Services\AccountRelayService;
use VentureDrake\LaravelCrm\Tests\Stubs\User;

it('correctly marks incomplete tasks as standby under standby leads while keeping completed tasks active', function () {
    $org = Organization::create([
        'external_id' => Str::uuid()->toString(),
        'name' => 'Acme Corporation',
    ]);

    $activeLead = Lead::create([
        'external_id' => Str::uuid()->toString(),
        'title' => 'Acme CEO',
        'organization_id' => $org->id,
        'relay_status' => 'active',
        'relay_order' => 1,
    ]);

    $standbyLead = Lead::create([
        'external_id' => Str::uuid()->toString(),
        'title' => 'Acme VP Eng',
        'organization_id' => $org->id,
        'relay_status' => 'standby',
        'relay_order' => 2,
    ]);

    $activeTask = Task::create([
        'external_id' => Str::uuid()->toString(),
        'name' => 'Active Outreach Call',
        'taskable_type' => Lead::class,
        'taskable_id' => $activeLead->id,
    ]);

    $standbyIncompleteTask = Task::create([
        'external_id' => Str::uuid()->toString(),
        'name' => 'Standby Follow Up',
        'taskable_type' => Lead::class,
        'taskable_id' => $standbyLead->id,
    ]);

    $standbyCompletedTask = Task::create([
        'external_id' => Str::uuid()->toString(),
        'name' => 'Past Intro Email',
        'taskable_type' => Lead::class,
        'taskable_id' => $standbyLead->id,
        'completed_at' => Carbon::now()->subDays(3),
    ]);

    expect($activeTask->isStandby())->toBeFalse()
        ->and($standbyIncompleteTask->isStandby())->toBeTrue()
        ->and($standbyCompletedTask->isStandby())->toBeFalse();
});

it('filters tasks by account relay status in TaskIndex component', function () {
    $user = User::first() ?: User::create([
        'name' => 'Test Agent',
        'email' => 'agent@example.com',
        'password' => bcrypt('secret'),
    ]);

    $org = Organization::create([
        'external_id' => Str::uuid()->toString(),
        'name' => 'Stark Industries',
    ]);

    $activeLead = Lead::create([
        'external_id' => Str::uuid()->toString(),
        'title' => 'Tony Stark',
        'organization_id' => $org->id,
        'relay_status' => 'active',
    ]);

    $standbyLead = Lead::create([
        'external_id' => Str::uuid()->toString(),
        'title' => 'Pepper Potts',
        'organization_id' => $org->id,
        'relay_status' => 'standby',
    ]);

    $activeTask = Task::create([
        'external_id' => Str::uuid()->toString(),
        'name' => 'Call Tony Stark',
        'taskable_type' => Lead::class,
        'taskable_id' => $activeLead->id,
    ]);

    $standbyTask = Task::create([
        'external_id' => Str::uuid()->toString(),
        'name' => 'Draft Email Pepper',
        'taskable_type' => Lead::class,
        'taskable_id' => $standbyLead->id,
    ]);

    $completedStandbyTask = Task::create([
        'external_id' => Str::uuid()->toString(),
        'name' => 'Completed Touchpoint Pepper',
        'taskable_type' => Lead::class,
        'taskable_id' => $standbyLead->id,
        'completed_at' => Carbon::now()->subDay(),
    ]);

    // 1. When filtered to active_only: excludes uncompleted standby task, includes active & completed
    Livewire::actingAs($user)
        ->test(TaskIndex::class, ['account_relay' => 'active_only'])
        ->assertSee('Call Tony Stark')
        ->assertSee('Completed Touchpoint Pepper')
        ->assertDontSee('Draft Email Pepper');

    // 2. When filtered to standby_only: includes only uncompleted standby tasks
    Livewire::actingAs($user)
        ->test(TaskIndex::class, ['account_relay' => 'standby_only'])
        ->assertSee('Draft Email Pepper')
        ->assertDontSee('Call Tony Stark');

    // 3. When viewing all: shows all and marks standby tasks
    Livewire::actingAs($user)
        ->test(TaskIndex::class, ['account_relay' => ''])
        ->assertSee('Call Tony Stark')
        ->assertSee('Draft Email Pepper')
        ->assertSee('Account Standby');
});

it('automatically soft-deletes incomplete tasks when a lead falls off during relay rotation', function () {
    $org = Organization::create([
        'external_id' => Str::uuid()->toString(),
        'name' => 'Wayne Enterprises',
    ]);

    $lead1 = Lead::create([
        'external_id' => Str::uuid()->toString(),
        'title' => 'Bruce Wayne',
        'organization_id' => $org->id,
        'relay_status' => 'active',
        'relay_order' => 1,
    ]);

    $lead2 = Lead::create([
        'external_id' => Str::uuid()->toString(),
        'title' => 'Lucius Fox',
        'organization_id' => $org->id,
        'relay_status' => 'standby',
        'relay_order' => 2,
    ]);

    $incompleteTask = Task::create([
        'external_id' => Str::uuid()->toString(),
        'name' => 'Send Bruce Proposal',
        'taskable_type' => Lead::class,
        'taskable_id' => $lead1->id,
    ]);

    $completedTask = Task::create([
        'external_id' => Str::uuid()->toString(),
        'name' => 'Initial LinkedIn Message to Bruce',
        'taskable_type' => Lead::class,
        'taskable_id' => $lead1->id,
        'completed_at' => Carbon::now()->subDays(5),
    ]);

    // Advance relay: Bruce falls off (unresponsive) -> Lucius is activated
    $service = app(AccountRelayService::class);
    $next = $service->rotateToNext($lead1, 'unresponsive');

    expect($next->id)->toBe($lead2->id)
        ->and($lead1->fresh()->relay_status)->toBe('fallen_off');

    // Incomplete task for Bruce should be soft-deleted by operational cog
    expect(Task::find($incompleteTask->id))->toBeNull()
        ->and(Task::withTrashed()->find($incompleteTask->id)->deleted_at)->not->toBeNull()
        // Completed task remains untouched ("unless completed")
        ->and(Task::find($completedTask->id))->not->toBeNull()
        ->and(Task::find($completedTask->id)->completed_at)->not->toBeNull();
});

it('cancels all incomplete tasks across the basket when company is marked disqualified', function () {
    $org = Organization::create([
        'external_id' => Str::uuid()->toString(),
        'name' => 'LexCorp',
    ]);

    $lead1 = Lead::create([
        'external_id' => Str::uuid()->toString(),
        'title' => 'Lex Luthor',
        'organization_id' => $org->id,
        'relay_status' => 'active',
    ]);

    $lead2 = Lead::create([
        'external_id' => Str::uuid()->toString(),
        'title' => 'Mercy Graves',
        'organization_id' => $org->id,
        'relay_status' => 'standby',
    ]);

    $task1 = Task::create([
        'external_id' => Str::uuid()->toString(),
        'name' => 'Call Lex',
        'taskable_type' => Lead::class,
        'taskable_id' => $lead1->id,
    ]);

    $task2 = Task::create([
        'external_id' => Str::uuid()->toString(),
        'name' => 'Email Mercy',
        'taskable_type' => Lead::class,
        'taskable_id' => $lead2->id,
    ]);

    $taskDone = Task::create([
        'external_id' => Str::uuid()->toString(),
        'name' => 'Researched LexCorp',
        'taskable_type' => Lead::class,
        'taskable_id' => $lead1->id,
        'completed_at' => Carbon::now()->subDays(2),
    ]);

    $service = app(AccountRelayService::class);
    $service->disqualifyBasket($org, 'Do Not Contact - Hostile');

    expect(Task::find($task1->id))->toBeNull()
        ->and(Task::find($task2->id))->toBeNull()
        ->and(Task::find($taskDone->id))->not->toBeNull();
});

it('activates a standby lead in parallel while keeping the primary colleague active and rebasing overdue tasks', function () {
    $user = User::first() ?: User::create([
        'name' => 'Test Agent',
        'email' => 'agent@example.com',
        'password' => bcrypt('secret'),
    ]);

    $org = Organization::create([
        'external_id' => Str::uuid()->toString(),
        'name' => 'Initech',
    ]);

    $activeLead = Lead::create([
        'external_id' => Str::uuid()->toString(),
        'title' => 'Bill Lumbergh',
        'organization_id' => $org->id,
        'relay_status' => 'active',
        'relay_order' => 1,
    ]);

    $standbyLead = Lead::create([
        'external_id' => Str::uuid()->toString(),
        'title' => 'Peter Gibbons',
        'organization_id' => $org->id,
        'relay_status' => 'standby',
        'relay_order' => 2,
    ]);

    $overdueStandbyTask = Task::create([
        'external_id' => Str::uuid()->toString(),
        'name' => 'Follow up with Peter',
        'taskable_type' => Lead::class,
        'taskable_id' => $standbyLead->id,
        'due_at' => Carbon::now()->subDays(5),
    ]);

    expect($overdueStandbyTask->fresh()->isStandby())->toBeTrue();

    Livewire::actingAs($user)
        ->test(LeadShow::class, ['lead' => $standbyLead])
        ->call('activateFromStandby');

    // Both leads remain active in parallel
    expect($activeLead->fresh()->relay_status)->toBe('active')
        ->and($standbyLead->fresh()->relay_status)->toBe('active')
        ->and($standbyLead->fresh()->relay_activated_at)->not->toBeNull()
        ->and($overdueStandbyTask->fresh()->isStandby())->toBeFalse()
        ->and($overdueStandbyTask->fresh()->due_at->isFuture())->toBeTrue();
});

it('bulk releases standby leads across the CRM while skipping disqualified companies', function () {
    $user = User::first() ?: User::create([
        'name' => 'Test Agent',
        'email' => 'agent@example.com',
        'password' => bcrypt('secret'),
    ]);

    $orgGood = Organization::create([
        'external_id' => Str::uuid()->toString(),
        'name' => 'Hooli',
    ]);

    $orgDnc = Organization::create([
        'external_id' => Str::uuid()->toString(),
        'name' => 'Pied Piper DNC',
    ]);
    $orgDnc->markDisqualified();

    $activeLead = Lead::create([
        'external_id' => Str::uuid()->toString(),
        'title' => 'Gavin Belson',
        'organization_id' => $orgGood->id,
        'relay_status' => 'active',
        'relay_order' => 1,
    ]);

    $standby1 = Lead::create([
        'external_id' => Str::uuid()->toString(),
        'title' => 'Jared Dunn',
        'organization_id' => $orgGood->id,
        'relay_status' => 'standby',
        'relay_order' => 2,
    ]);

    $standby2 = Lead::create([
        'external_id' => Str::uuid()->toString(),
        'title' => 'Richard Hendricks',
        'organization_id' => $orgGood->id,
        'relay_status' => 'standby',
        'relay_order' => 3,
    ]);

    $standbyDnc = Lead::create([
        'external_id' => Str::uuid()->toString(),
        'title' => 'Erlich Bachman',
        'organization_id' => $orgDnc->id,
        'relay_status' => 'standby',
        'relay_order' => 2,
    ]);

    Livewire::actingAs($user)
        ->test(LeadIndex::class)
        ->call('releaseAllStandby');

    expect($activeLead->fresh()->relay_status)->toBe('active')
        ->and($standby1->fresh()->relay_status)->toBe('active')
        ->and($standby2->fresh()->relay_status)->toBe('active')
        ->and($standbyDnc->fresh()->relay_status)->toBe('standby');
});
