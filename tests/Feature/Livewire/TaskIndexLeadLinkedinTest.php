<?php

use Illuminate\Support\Str;
use Livewire\Livewire;
use VentureDrake\LaravelCrm\Livewire\Tasks\TaskIndex;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Models\Task;
use VentureDrake\LaravelCrm\Tests\Stubs\User;

it('resolves linkedin_url accessor on lead, person, and task', function () {
    $personWithUrl = Person::create([
        'external_id' => Str::uuid()->toString(),
        'first_name' => 'Sarah',
        'last_name' => 'Connor',
        'linkedin' => 'https://linkedin.com/in/sarah-connor',
    ]);

    $leadDirect = Lead::create([
        'external_id' => Str::uuid()->toString(),
        'title' => 'Cyberdyne Lead',
        'linkedin' => 'linkedin.com/in/direct-lead',
    ]);

    $leadViaPerson = Lead::create([
        'external_id' => Str::uuid()->toString(),
        'title' => 'Resistance Lead',
        'person_id' => $personWithUrl->id,
    ]);

    $taskDirectLead = Task::create([
        'external_id' => Str::uuid()->toString(),
        'name' => 'Connect on LinkedIn',
        'taskable_type' => Lead::class,
        'taskable_id' => $leadDirect->id,
    ]);

    $taskPersonLead = Task::create([
        'external_id' => Str::uuid()->toString(),
        'name' => 'Send intro DM',
        'taskable_type' => Lead::class,
        'taskable_id' => $leadViaPerson->id,
    ]);

    $taskDirectPerson = Task::create([
        'external_id' => Str::uuid()->toString(),
        'name' => 'Outreach to Sarah',
        'taskable_type' => Person::class,
        'taskable_id' => $personWithUrl->id,
    ]);

    expect($personWithUrl->linkedin_url)->toBe('https://linkedin.com/in/sarah-connor')
        ->and($leadDirect->linkedin_url)->toBe('https://linkedin.com/in/direct-lead')
        ->and($leadViaPerson->linkedin_url)->toBe('https://linkedin.com/in/sarah-connor')
        ->and($taskDirectLead->linkedin_url)->toBe('https://linkedin.com/in/direct-lead')
        ->and($taskPersonLead->linkedin_url)->toBe('https://linkedin.com/in/sarah-connor')
        ->and($taskDirectPerson->linkedin_url)->toBe('https://linkedin.com/in/sarah-connor')
        ->and($taskDirectLead->lead->id)->toBe($leadDirect->id);
});

it('surfaces the linkedin url of the lead in the task list without navigating to detail pages', function () {
    $user = User::create(['name' => 'Sales Agent', 'email' => 'agent_'.Str::random(5).'@example.com']);
    $this->actingAs($user);

    $lead = Lead::create([
        'external_id' => Str::uuid()->toString(),
        'title' => 'Quantum Leap Tech',
        'linkedin' => 'https://www.linkedin.com/in/sam-beckett-leap',
    ]);

    $task = Task::create([
        'external_id' => Str::uuid()->toString(),
        'name' => 'Send LinkedIn Connection Request',
        'description' => 'Targeting CTO for AI workflow demo',
        'taskable_type' => Lead::class,
        'taskable_id' => $lead->id,
        'user_assigned_id' => $user->id,
    ]);

    Livewire::test(TaskIndex::class)
        ->assertSee('Quantum Leap Tech')
        ->assertSee('https://www.linkedin.com/in/sam-beckett-leap')
        ->assertSee('LinkedIn Profile')
        ->assertSeeHtml('href="https://www.linkedin.com/in/sam-beckett-leap"')
        ->assertSeeHtml('target="_blank"')
        ->assertSeeHtml('onclick="event.stopPropagation();"');
});

it('surfaces linkedin url on task list when present on the lead contact person', function () {
    $user = User::create(['name' => 'Sales Agent 2', 'email' => 'agent2_'.Str::random(5).'@example.com']);
    $this->actingAs($user);

    $person = Person::create([
        'external_id' => Str::uuid()->toString(),
        'first_name' => 'Elena',
        'last_name' => 'Rostova',
        'linkedin' => 'https://www.linkedin.com/in/elena-rostova-growth',
    ]);

    $lead = Lead::create([
        'external_id' => Str::uuid()->toString(),
        'title' => 'Apex Global Solutions',
        'person_id' => $person->id,
    ]);

    Task::create([
        'external_id' => Str::uuid()->toString(),
        'name' => 'Send introductory DM to Elena',
        'taskable_type' => Lead::class,
        'taskable_id' => $lead->id,
        'user_assigned_id' => $user->id,
    ]);

    Livewire::test(TaskIndex::class)
        ->assertSee('Apex Global Solutions')
        ->assertSee('Elena Rostova')
        ->assertSee('https://www.linkedin.com/in/elena-rostova-growth')
        ->assertSeeHtml('href="https://www.linkedin.com/in/elena-rostova-growth"');
});

it('filters tasks by linkedin profile availability in task list', function () {
    $user = User::create(['name' => 'Filter Agent', 'email' => 'filter_'.Str::random(5).'@example.com']);
    $this->actingAs($user);

    $leadWithLinkedin = Lead::create([
        'external_id' => Str::uuid()->toString(),
        'title' => 'LinkedIn Connected Lead',
        'linkedin' => 'https://linkedin.com/in/connected-lead',
    ]);

    $leadWithoutLinkedin = Lead::create([
        'external_id' => Str::uuid()->toString(),
        'title' => 'No LinkedIn Lead',
        'linkedin' => null,
    ]);

    $taskWithLinkedin = Task::create([
        'external_id' => Str::uuid()->toString(),
        'name' => 'Task With LinkedIn',
        'taskable_type' => Lead::class,
        'taskable_id' => $leadWithLinkedin->id,
    ]);

    $taskWithoutLinkedin = Task::create([
        'external_id' => Str::uuid()->toString(),
        'name' => 'Task Without LinkedIn',
        'taskable_type' => Lead::class,
        'taskable_id' => $leadWithoutLinkedin->id,
    ]);

    $testYes = Livewire::test(TaskIndex::class)->set('has_linkedin', 'yes');
    $tasksYes = $testYes->instance()->tasks();
    expect($tasksYes->pluck('id'))->toContain($taskWithLinkedin->id)
        ->and($tasksYes->pluck('id'))->not->toContain($taskWithoutLinkedin->id);

    $testNo = Livewire::test(TaskIndex::class)->set('has_linkedin', 'no');
    $tasksNo = $testNo->instance()->tasks();
    expect($tasksNo->pluck('id'))->toContain($taskWithoutLinkedin->id)
        ->and($tasksNo->pluck('id'))->not->toContain($taskWithLinkedin->id);
});

it('finds tasks by linkedin url in search', function () {
    $user = User::create(['name' => 'Search Agent', 'email' => 'search_'.Str::random(5).'@example.com']);
    $this->actingAs($user);

    $uniqueSlug = 'custom-unique-profile-'.Str::random(8);

    $lead = Lead::create([
        'external_id' => Str::uuid()->toString(),
        'title' => 'Target Enterprise Lead',
        'linkedin' => 'https://linkedin.com/in/'.$uniqueSlug,
    ]);

    $targetTask = Task::create([
        'external_id' => Str::uuid()->toString(),
        'name' => 'General outreach task',
        'taskable_type' => Lead::class,
        'taskable_id' => $lead->id,
    ]);

    $otherTask = Task::create([
        'external_id' => Str::uuid()->toString(),
        'name' => 'Other outreach task',
    ]);

    $test = Livewire::test(TaskIndex::class)->set('search', $uniqueSlug);
    $results = $test->instance()->tasks();
    expect($results->pluck('id'))->toContain($targetTask->id)
        ->and($results->pluck('id'))->not->toContain($otherTask->id);
});
