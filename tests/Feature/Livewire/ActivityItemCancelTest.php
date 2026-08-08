<?php

use Livewire\Livewire;
use VentureDrake\LaravelCrm\Livewire\Calls\CallItem;
use VentureDrake\LaravelCrm\Livewire\Lunches\LunchItem;
use VentureDrake\LaravelCrm\Livewire\Meetings\MeetingItem;
use VentureDrake\LaravelCrm\Livewire\Notes\NoteItem;
use VentureDrake\LaravelCrm\Livewire\Tasks\TaskItem;
use VentureDrake\LaravelCrm\Models\Call;
use VentureDrake\LaravelCrm\Models\Lunch;
use VentureDrake\LaravelCrm\Models\Meeting;
use VentureDrake\LaravelCrm\Models\Note;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Models\Task;

/**
 * Render-stub subclasses.
 *
 * The activity blades reach for tables the minimal TestSchema does not ship. Overriding
 * only render() leaves edit()/cancel() -- the methods under test -- fully intact.
 */
class CancelTaskItem extends TaskItem
{
    public function render()
    {
        return '<div></div>';
    }
}
class CancelNoteItem extends NoteItem
{
    public function render()
    {
        return '<div></div>';
    }
}
class CancelCallItem extends CallItem
{
    public function render()
    {
        return '<div></div>';
    }
}
class CancelMeetingItem extends MeetingItem
{
    public function render()
    {
        return '<div></div>';
    }
}
class CancelLunchItem extends LunchItem
{
    public function render()
    {
        return '<div></div>';
    }
}

beforeEach(function () {
    $this->actingAsUserWithPermissions([
        'view crm tasks', 'edit crm tasks',
        'view crm notes', 'edit crm notes',
        'view crm calls', 'edit crm calls',
        'view crm meetings', 'edit crm meetings',
        'view crm lunches', 'edit crm lunches',
    ]);
});

/*
 * Every inline activity card follows the same edit -> change -> cancel shape, so the
 * cases are generated from one table rather than written out five times.
 *
 * Each entry: [component, mount property, factory, primary field, its stored value,
 * secondary field, its stored value].
 */
$cases = [
    'task' => [
        CancelTaskItem::class, 'task',
        fn () => Task::create([
            'name' => 'Original name',
            'description' => 'Original description',
            'start_at' => '2026-08-08 09:00:00',
        ]),
        'name', 'Original name',
        'start_at', '2026-08-08T09:00',
    ],
    'note' => [
        CancelNoteItem::class, 'note',
        // crm_notes.noteable_type is NOT NULL, so a note needs its parent record.
        fn () => Person::create(['first_name' => 'Cancel Parent'])->notes()->create([
            'content' => 'Original content',
            'noted_at' => '2026-08-08 09:00:00',
        ]),
        'content', 'Original content',
        'noted_at', '2026-08-08 09:00:00',
    ],
    'call' => [
        CancelCallItem::class, 'call',
        fn () => Call::create([
            'name' => 'Original name',
            'description' => 'Original description',
            'start_at' => '2026-08-08 09:00:00',
            'finish_at' => '2026-08-08 10:00:00',
        ]),
        'name', 'Original name',
        'start_at', '2026-08-08T09:00',
    ],
    'meeting' => [
        CancelMeetingItem::class, 'meeting',
        fn () => Meeting::create([
            'name' => 'Original name',
            'description' => 'Original description',
            'start_at' => '2026-08-08 09:00:00',
            'finish_at' => '2026-08-08 10:00:00',
        ]),
        'name', 'Original name',
        'start_at', '2026-08-08T09:00',
    ],
    'lunch' => [
        CancelLunchItem::class, 'lunch',
        fn () => Lunch::create([
            'name' => 'Original name',
            'description' => 'Original description',
            'start_at' => '2026-08-08 09:00:00',
            'finish_at' => '2026-08-08 10:00:00',
        ]),
        'name', 'Original name',
        'start_at', '2026-08-08T09:00',
    ],
];

foreach ($cases as $label => [$component, $property, $make, $field, $original, $extraField, $extraOriginal]) {
    it("restores the {$label} fields when an inline edit is cancelled", function () use (
        $component, $property, $make, $field, $original, $extraField, $extraOriginal
    ) {
        $record = $make();

        Livewire::test($component, [$property => $record])
            ->call('edit')
            ->set($field, 'Tampered value')
            ->set($extraField, '2027-01-01T12:00')
            ->call('cancel')
            ->assertSet($field, $original)
            ->assertSet($extraField, $extraOriginal)
            ->assertSet('editing', false);
    });

    it("leaves the stored {$label} untouched when an inline edit is cancelled", function () use (
        $component, $property, $make, $field
    ) {
        $record = $make();
        $before = $record->fresh()->toArray();

        Livewire::test($component, [$property => $record])
            ->call('edit')
            ->set($field, 'Tampered value')
            ->call('cancel');

        expect($record->fresh()->toArray())->toBe($before);
    });
}
