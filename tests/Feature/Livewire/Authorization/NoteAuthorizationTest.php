<?php

use Livewire\Livewire;
use VentureDrake\LaravelCrm\Livewire\Notes\NoteItem;
use VentureDrake\LaravelCrm\Livewire\Notes\NoteRelated;
use VentureDrake\LaravelCrm\Models\Note;
use VentureDrake\LaravelCrm\Models\Person;

/**
 * Render-stub subclasses.
 *
 * Livewire renders a component on mount, and the sub-item / related blades reach for
 * tables the minimal TestSchema does not ship. Overriding only render() leaves the real
 * action methods -- and the $this->authorize() guards inside them -- completely intact,
 * so these tests still exercise the production authorization path against the real
 * policies.
 */
class AuthzNoteItem extends NoteItem
{
    public function render()
    {
        return '<div></div>';
    }
}
class AuthzNoteRelated extends NoteRelated
{
    public function render()
    {
        return '<div></div>';
    }
}

function authzNote(): Note
{
    $parent = Person::create(['first_name' => 'Authz Parent']);

    return Note::create([
        'content' => 'Original content',
        'noteable_type' => $parent->getMorphClass(),
        'noteable_id' => $parent->id,
    ]);
}

it('forbids updating a note without the edit permission and leaves the record intact', function () {
    $this->actingAsUserWithPermissions(['view crm notes']);
    $record = authzNote();

    Livewire::test(AuthzNoteItem::class, ['note' => $record])
        ->set('content', 'Tampered')
        ->call('update')
        ->assertForbidden();

    expect($record->fresh()->content)->toBe('Original content');
});

it('allows updating a note with the edit permission', function () {
    $this->actingAsUserWithPermissions(['view crm notes', 'edit crm notes']);
    $record = authzNote();

    Livewire::test(AuthzNoteItem::class, ['note' => $record])
        ->set('content', 'Updated content')
        ->call('update')
        ->assertOk();

    expect($record->fresh()->content)->toBe('Updated content');
});

it('forbids pinning a note without the edit permission', function () {
    $this->actingAsUserWithPermissions(['view crm notes']);
    $record = authzNote();

    Livewire::test(AuthzNoteItem::class, ['note' => $record])
        ->call('pin')
        ->assertForbidden();

    expect((bool) $record->fresh()->pinned)->toBeFalse();
});

it('allows pinning and unpinning a note with the edit permission', function () {
    $this->actingAsUserWithPermissions(['view crm notes', 'edit crm notes']);
    $record = authzNote();

    Livewire::test(AuthzNoteItem::class, ['note' => $record])
        ->call('pin')
        ->assertOk();

    expect((bool) $record->fresh()->pinned)->toBeTrue();

    Livewire::test(AuthzNoteItem::class, ['note' => $record->fresh()])
        ->call('unpin')
        ->assertOk();

    expect((bool) $record->fresh()->pinned)->toBeFalse();
});

it('forbids unpinning a note without the edit permission', function () {
    $this->actingAsUserWithPermissions(['view crm notes']);
    $record = authzNote();
    $record->update(['pinned' => 1]);

    Livewire::test(AuthzNoteItem::class, ['note' => $record->fresh()])
        ->call('unpin')
        ->assertForbidden();

    expect((bool) $record->fresh()->pinned)->toBeTrue();
});

it('forbids deleting a note without the delete permission and leaves the record intact', function () {
    $this->actingAsUserWithPermissions(['view crm notes']);
    $record = authzNote();

    Livewire::test(AuthzNoteItem::class, ['note' => $record])
        ->call('delete')
        ->assertForbidden();

    expect(Note::find($record->id))->not->toBeNull();
});

it('allows deleting a note with the delete permission', function () {
    $this->actingAsUserWithPermissions(['view crm notes', 'delete crm notes']);
    $record = authzNote();

    Livewire::test(AuthzNoteItem::class, ['note' => $record])
        ->call('delete')
        ->assertOk();

    expect(Note::find($record->id))->toBeNull();
});

it('forbids adding a related note without the create permission and creates no record', function () {
    $this->actingAsUserWithPermissions(['view crm notes']);
    $parent = Person::create(['first_name' => 'Authz Parent']);
    $before = Note::count();

    Livewire::test(AuthzNoteRelated::class, ['model' => $parent])
        ->set('content', 'Related note')
        ->call('save')
        ->assertForbidden();

    expect(Note::count())->toBe($before);
});

it('allows adding a related note with the create permission', function () {
    $this->actingAsUserWithPermissions(['view crm notes', 'create crm notes']);
    $parent = Person::create(['first_name' => 'Authz Parent']);

    Livewire::test(AuthzNoteRelated::class, ['model' => $parent])
        ->set('content', 'Related note')
        ->call('save')
        ->assertOk();

    expect($parent->notes()->count())->toBe(1);
});
