<?php

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Livewire\Livewire;
use VentureDrake\LaravelCrm\Livewire\Tasks\TaskCreate;
use VentureDrake\LaravelCrm\Livewire\Tasks\TaskEdit;
use VentureDrake\LaravelCrm\Livewire\Tasks\TaskItem;
use VentureDrake\LaravelCrm\Livewire\Tasks\TaskRelated;
use VentureDrake\LaravelCrm\Models\Order;
use VentureDrake\LaravelCrm\Models\Pipeline;
use VentureDrake\LaravelCrm\Models\Setting;
use VentureDrake\LaravelCrm\Models\Task;
use VentureDrake\LaravelCrm\Services\TaskService;

/**
 * Render-stub subclasses.
 *
 * The task blades reach for tables the minimal TestSchema does not ship. Overriding only
 * render() leaves the real action methods -- and the $this->authorize() guards and
 * validation rules inside them -- completely intact.
 */
class StartAtTaskRelated extends TaskRelated
{
    public function render()
    {
        return '<div></div>';
    }
}
class StartAtTaskItem extends TaskItem
{
    public function render()
    {
        return '<div></div>';
    }
}
class StartAtTaskEdit extends TaskEdit
{
    public function render()
    {
        return '<div></div>';
    }
}
class StartAtTaskCreate extends TaskCreate
{
    public function render()
    {
        return '<div></div>';
    }
}

beforeEach(function () {
    Setting::updateOrCreate(['name' => 'currency'], ['value' => 'USD']);

    $pipeline = Pipeline::create([
        'external_id' => Str::uuid()->toString(),
        'name' => 'Order Pipeline',
        'model' => Order::class,
    ]);

    foreach (['Pending', 'Closed Won'] as $order => $stage) {
        $pipeline->pipelineStages()->create([
            'external_id' => Str::uuid()->toString(),
            'name' => $stage,
            'order' => $order,
        ]);
    }

    $this->actingAsUserWithPermissions([
        'view crm tasks', 'create crm tasks', 'edit crm tasks',
    ]);
});

test('a task added to an order persists its start date', function () {
    $order = Order::create(['currency' => 'USD']);

    Livewire::test(StartAtTaskRelated::class, ['model' => $order])
        ->set('name', 'Prepare the shipment')
        ->set('start_at', '2026-08-08T09:00')
        ->set('due_at', '2026-08-09T17:00')
        ->call('save')
        ->assertHasNoErrors();

    $task = $order->tasks()->first();

    expect($task->start_at->toDateTimeString())->toBe('2026-08-08 09:00:00');
    expect($task->due_at->toDateTimeString())->toBe('2026-08-09 17:00:00');
});

test('the start date stays optional', function () {
    $order = Order::create(['currency' => 'USD']);

    Livewire::test(StartAtTaskRelated::class, ['model' => $order])
        ->set('name', 'Chase the invoice')
        ->set('due_at', '2026-08-09T17:00')
        ->call('save')
        ->assertHasNoErrors();

    $task = $order->tasks()->first();

    expect($task->start_at)->toBeNull();
    expect($task->due_at->toDateTimeString())->toBe('2026-08-09 17:00:00');
});

test('a due date before the start date is rejected', function () {
    $order = Order::create(['currency' => 'USD']);

    Livewire::test(StartAtTaskRelated::class, ['model' => $order])
        ->set('name', 'Backwards task')
        ->set('start_at', '2026-08-10T09:00')
        ->set('due_at', '2026-08-09T17:00')
        ->call('save')
        ->assertHasErrors(['due_at']);

    expect($order->tasks()->count())->toBe(0);
});

test('a past due date is allowed when no start date is given', function () {
    $order = Order::create(['currency' => 'USD']);

    Livewire::test(StartAtTaskRelated::class, ['model' => $order])
        ->set('name', 'Follow up on the overdue invoice')
        ->set('due_at', now()->subWeek()->format('Y-m-d\TH:i'))
        ->call('save')
        ->assertHasNoErrors();

    expect($order->tasks()->count())->toBe(1);
});

test('the inline card editor preserves both timestamps when only the name changes', function () {
    $task = Task::create([
        'name' => 'Original name',
        'start_at' => '2026-08-08 09:00:00',
        'due_at' => '2026-08-09 17:00:00',
    ]);

    Livewire::test(StartAtTaskItem::class, ['task' => $task])
        ->call('edit')
        ->set('name', 'Renamed')
        ->call('update')
        ->assertHasNoErrors();

    $task->refresh();

    expect($task->name)->toBe('Renamed');
    expect($task->start_at->toDateTimeString())->toBe('2026-08-08 09:00:00');
    expect($task->due_at->toDateTimeString())->toBe('2026-08-09 17:00:00');
});

test('cancelling the inline card editor restores the start date', function () {
    $task = Task::create([
        'name' => 'Original name',
        'start_at' => '2026-08-08 09:00:00',
    ]);

    Livewire::test(StartAtTaskItem::class, ['task' => $task])
        ->assertSet('start_at', '2026-08-08T09:00')
        ->call('edit')
        ->set('start_at', '2026-12-25T09:00')
        ->call('cancel')
        ->assertSet('start_at', '2026-08-08T09:00');

    expect($task->fresh()->start_at->toDateTimeString())->toBe('2026-08-08 09:00:00');
});

test('the edit page hydrates the full due date and time', function () {
    $task = Task::create([
        'name' => 'Timed task',
        'start_at' => '2026-08-08 09:00:00',
        'due_at' => '2026-08-08 14:30:00',
    ]);

    Livewire::test(StartAtTaskEdit::class, ['task' => $task])
        ->assertSet('start_at', '2026-08-08T09:00')
        ->assertSet('due_at', '2026-08-08T14:30');
});

test('the service passes the start date through on create and update', function () {
    $task = app(TaskService::class)->create(new Request([
        'name' => 'Serviced task',
        'start_at' => '2026-08-08 09:00:00',
        'due_at' => '2026-08-09 17:00:00',
    ]));

    expect($task->start_at->toDateTimeString())->toBe('2026-08-08 09:00:00');

    app(TaskService::class)->update(new Request([
        'name' => 'Serviced task',
        'start_at' => '2026-08-11 11:00:00',
        'due_at' => '2026-08-12 17:00:00',
    ]), $task);

    expect($task->fresh()->start_at->toDateTimeString())->toBe('2026-08-11 11:00:00');
});

/*
 * The create and edit pages hand their public properties to TaskService untouched, so the
 * datetime-local "Y-m-d\TH:i" strings reach the model with the T still in them. These lock
 * in that the datetime cast resolves that form to the right stored value -- normalising it
 * the way the inline editors do is not what keeps this path correct.
 */
test('the create page stores both timestamps from datetime-local values', function () {
    Livewire::test(StartAtTaskCreate::class)
        ->set('name', 'Created task')
        ->set('start_at', '2026-08-08T09:00')
        ->set('due_at', '2026-08-09T17:00')
        ->call('save')
        ->assertHasNoErrors();

    $task = Task::where('name', 'Created task')->first();

    expect($task->start_at->toDateTimeString())->toBe('2026-08-08 09:00:00');
    expect($task->due_at->toDateTimeString())->toBe('2026-08-09 17:00:00');
});

test('the create page rejects a due date before the start date', function () {
    Livewire::test(StartAtTaskCreate::class)
        ->set('name', 'Backwards task')
        ->set('start_at', '2026-08-10T09:00')
        ->set('due_at', '2026-08-09T17:00')
        ->call('save')
        ->assertHasErrors(['due_at']);

    expect(Task::where('name', 'Backwards task')->count())->toBe(0);
});

test('the edit page stores both timestamps from datetime-local values', function () {
    $task = Task::create([
        'name' => 'Edited task',
        'start_at' => '2026-08-08 09:00:00',
        'due_at' => '2026-08-09 17:00:00',
    ]);

    Livewire::test(StartAtTaskEdit::class, ['task' => $task])
        ->set('start_at', '2026-08-11T11:15')
        ->set('due_at', '2026-08-12T16:45')
        ->call('save')
        ->assertHasNoErrors();

    $task->refresh();

    expect($task->start_at->toDateTimeString())->toBe('2026-08-11 11:15:00');
    expect($task->due_at->toDateTimeString())->toBe('2026-08-12 16:45:00');
});

/*
 * Rendered against the real blades, not the stubs above: all three task forms pull the
 * start/due pair from the one schedule-fields partial, so none of them can drift back to a
 * date-only picker or lose the 15 minute snapping on its own.
 */
test('every task form renders the shared schedule fields', function () {
    $task = Task::create(['name' => 'Rendered task', 'start_at' => '2026-08-08 09:00:00']);

    foreach ([
        Livewire::test(TaskCreate::class),
        Livewire::test(TaskEdit::class, ['task' => $task]),
        Livewire::test(TaskItem::class, ['task' => $task])->call('edit'),
    ] as $component) {
        $component
            ->assertSee('snap15', false)
            ->assertSee('type="datetime-local"', false);
    }
});
