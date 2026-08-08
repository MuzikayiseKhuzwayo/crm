<?php

use Livewire\Livewire;
use VentureDrake\LaravelCrm\Http\Livewire\Components\LiveNote;
use VentureDrake\LaravelCrm\Http\Livewire\Fields\CreateOrEdit;
use VentureDrake\LaravelCrm\Http\Livewire\LiveDealBoard;
use VentureDrake\LaravelCrm\Http\Livewire\LiveNotes;
use VentureDrake\LaravelCrm\Http\Livewire\LiveRelatedPerson;
use VentureDrake\LaravelCrm\Http\Livewire\PayInvoice;
use VentureDrake\LaravelCrm\Models\Deal;
use VentureDrake\LaravelCrm\Models\Field;
use VentureDrake\LaravelCrm\Models\Invoice;
use VentureDrake\LaravelCrm\Models\Note;
use VentureDrake\LaravelCrm\Models\Organization;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Models\Pipeline;
use VentureDrake\LaravelCrm\Models\PipelineStage;

/*
 * Representative coverage of the src/Http/Livewire guards.
 *
 * That tree is the pre-v2 Livewire codebase. No package view renders it any more, but
 * all 40+ components are still registered by name in LaravelCrmServiceProvider, so a
 * host app running published v1 views can invoke every one of them -- and until now
 * they carried no authorization at all. One component of each shape is covered here:
 * an item component, a related-record creator, a relationship linker, a document
 * action, a kanban board, and the dual-branch custom-field form.
 *
 * The components predate Livewire 3, so their success paths still call the removed
 * $this->emit() / $this->dispatchBrowserEvent() helpers. The stubs below no-op those
 * two shims (and render()) so an allowed call can be asserted end to end; nothing else
 * is replaced, and every $this->authorize() runs for real against the real policies.
 * The denial paths never reach the shims -- the guard aborts first, which is the point.
 */
trait AuthzLegacyStub
{
    public function render()
    {
        return '<div></div>';
    }

    public function emit(...$params) {}

    public function dispatchBrowserEvent(...$params) {}
}

class AuthzLiveNote extends LiveNote
{
    use AuthzLegacyStub;

    /**
     * LiveNote::mount() probes $this->settingService->get('show_related_activity')->value,
     * but SettingService::get() returns a scalar -- the legacy tree has rotted against it,
     * so mount() cannot complete for any setting value. mount() is not the authorization
     * surface; update()/delete() are, and they run here completely untouched.
     */
    public function mount(Note $note, $view = 'note')
    {
        $this->note = $note;
        $this->content = $note->content;
        $this->view = $view;
    }
}
class AuthzLiveNotes extends LiveNotes
{
    use AuthzLegacyStub;
}
class AuthzLiveRelatedPerson extends LiveRelatedPerson
{
    use AuthzLegacyStub;
}
class AuthzPayInvoice extends PayInvoice
{
    use AuthzLegacyStub;
}
class AuthzLiveDealBoard extends LiveDealBoard
{
    use AuthzLegacyStub;
}
class AuthzFieldCreateOrEdit extends CreateOrEdit
{
    use AuthzLegacyStub;
}

function authzLegacyNote(): Note
{
    $parent = Person::create(['first_name' => 'Legacy parent']);

    return Note::create([
        'content' => 'Original content',
        'noteable_type' => $parent->getMorphClass(),
        'noteable_id' => $parent->id,
    ]);
}

/*
 * ---------------------------------------------------------------------------
 * Item component -- Components/LiveNote.
 * ---------------------------------------------------------------------------
 */

it('forbids a legacy note update without the edit permission and leaves the record intact', function () {
    $this->actingAsUserWithPermissions(['view crm notes']);
    $note = authzLegacyNote();

    Livewire::test(AuthzLiveNote::class, ['note' => $note])
        ->set('content', 'Tampered')
        ->call('update')
        ->assertForbidden();

    expect($note->fresh()->content)->toBe('Original content');
});

it('allows a legacy note update with the edit permission', function () {
    $this->actingAsUserWithPermissions(['view crm notes', 'edit crm notes']);
    $note = authzLegacyNote();

    Livewire::test(AuthzLiveNote::class, ['note' => $note])
        ->set('content', 'Updated content')
        ->call('update')
        ->assertOk();

    expect($note->fresh()->content)->toBe('Updated content');
});

it('forbids a legacy note delete without the delete permission', function () {
    $this->actingAsUserWithPermissions(['view crm notes']);
    $note = authzLegacyNote();

    Livewire::test(AuthzLiveNote::class, ['note' => $note])
        ->call('delete')
        ->assertForbidden();

    expect(Note::find($note->id))->not->toBeNull();
});

it('allows a legacy note delete with the delete permission', function () {
    $this->actingAsUserWithPermissions(['view crm notes', 'delete crm notes']);
    $note = authzLegacyNote();

    Livewire::test(AuthzLiveNote::class, ['note' => $note])
        ->call('delete')
        ->assertOk();

    expect(Note::find($note->id))->toBeNull();
});

/*
 * ---------------------------------------------------------------------------
 * Related-record creator -- LiveNotes::create.
 * ---------------------------------------------------------------------------
 */

it('forbids creating a legacy related note without the create permission', function () {
    $this->actingAsUserWithPermissions(['view crm notes']);
    $parent = Person::create(['first_name' => 'Legacy parent']);
    $before = Note::count();

    Livewire::test(AuthzLiveNotes::class, ['model' => $parent])
        ->set('content', 'Denied note')
        ->call('create')
        ->assertForbidden();

    expect(Note::count())->toBe($before);
});

it('allows creating a legacy related note with the create permission', function () {
    $this->actingAsUserWithPermissions(['view crm notes', 'create crm notes']);
    $parent = Person::create(['first_name' => 'Legacy parent']);

    Livewire::test(AuthzLiveNotes::class, ['model' => $parent])
        ->set('content', 'Allowed note')
        ->call('create')
        ->assertOk();

    expect($parent->notes()->count())->toBe(1);
});

/*
 * ---------------------------------------------------------------------------
 * Relationship linker -- LiveRelatedPerson. This one re-points the person's own
 * organization_id rather than writing a join row, so both records are authorized:
 * the organization gaining or losing a member, and the person actually being
 * written. The branch that invents a new person adds a create check on top, and
 * remove() resolves its id through the bound organization's own people.
 * ---------------------------------------------------------------------------
 */

it('forbids linking a person to an organization without the organization edit permission', function () {
    $this->actingAsUserWithPermissions(['view crm organizations', 'create crm people']);

    $organization = Organization::create(['name' => 'Legacy org']);
    $person = Person::create(['first_name' => 'Unlinked']);

    Livewire::test(AuthzLiveRelatedPerson::class, ['model' => $organization])
        ->set('person_id', $person->id)
        ->set('person_name', 'Unlinked')
        ->call('link')
        ->assertForbidden();

    expect($person->fresh()->organization_id)->toBeNull();
});

it('forbids linking an existing person without the people edit permission', function () {
    // The organization guard passes. Linking rewrites the person's organization_id,
    // so a caller who may edit the org but not people must still be stopped.
    $this->actingAsUserWithPermissions(['view crm organizations', 'edit crm organizations']);

    $organization = Organization::create(['name' => 'Legacy org']);
    $person = Person::create(['first_name' => 'Unlinked']);

    Livewire::test(AuthzLiveRelatedPerson::class, ['model' => $organization])
        ->set('person_id', $person->id)
        ->set('person_name', 'Unlinked')
        ->call('link')
        ->assertForbidden();

    expect($person->fresh()->organization_id)->toBeNull();
});

it('allows linking an existing person with both edit permissions', function () {
    $this->actingAsUserWithPermissions([
        'view crm organizations', 'edit crm organizations', 'edit crm people',
    ]);

    $organization = Organization::create(['name' => 'Legacy org']);
    $person = Person::create(['first_name' => 'Unlinked']);

    Livewire::test(AuthzLiveRelatedPerson::class, ['model' => $organization])
        ->set('person_id', $person->id)
        ->set('person_name', 'Unlinked')
        ->call('link')
        ->assertOk();

    expect((int) $person->fresh()->organization_id)->toBe($organization->id);
});

it('forbids inventing a new person on link without the people create permission', function () {
    // The organization guard passes; the second guard on the create branch is what
    // must stop a caller who may edit the org but not mint contacts.
    $this->actingAsUserWithPermissions(['view crm organizations', 'edit crm organizations']);

    $organization = Organization::create(['name' => 'Legacy org']);
    $before = Person::count();

    Livewire::test(AuthzLiveRelatedPerson::class, ['model' => $organization])
        ->set('person_name', 'Brand New Person')
        ->call('link')
        ->assertForbidden();

    expect(Person::count())->toBe($before);
});

it('forbids unlinking a person without the organization edit permission', function () {
    $this->actingAsUserWithPermissions(['view crm organizations']);

    $organization = Organization::create(['name' => 'Legacy org']);
    $person = Person::create(['first_name' => 'Linked', 'organization_id' => $organization->id]);

    Livewire::test(AuthzLiveRelatedPerson::class, ['model' => $organization])
        ->call('remove', $person->id)
        ->assertForbidden();

    expect((int) $person->fresh()->organization_id)->toBe($organization->id);
});

it('forbids unlinking a person without the people edit permission', function () {
    $this->actingAsUserWithPermissions(['view crm organizations', 'edit crm organizations']);

    $organization = Organization::create(['name' => 'Legacy org']);
    $person = Person::create(['first_name' => 'Linked', 'organization_id' => $organization->id]);

    Livewire::test(AuthzLiveRelatedPerson::class, ['model' => $organization])
        ->call('remove', $person->id)
        ->assertForbidden();

    expect((int) $person->fresh()->organization_id)->toBe($organization->id);
});

it('allows unlinking a person with both edit permissions', function () {
    $this->actingAsUserWithPermissions([
        'view crm organizations', 'edit crm organizations', 'edit crm people',
    ]);

    $organization = Organization::create(['name' => 'Legacy org']);
    $person = Person::create(['first_name' => 'Linked', 'organization_id' => $organization->id]);

    Livewire::test(AuthzLiveRelatedPerson::class, ['model' => $organization])
        ->call('remove', $person->id)
        ->assertOk();

    expect($person->fresh()->organization_id)->toBeNull();
});

it('ignores an unlink for a person belonging to a different organization', function () {
    // remove() resolves the id through the bound organization's own people, so an
    // id naming someone else's contact matches nothing and writes nothing.
    $this->actingAsUserWithPermissions([
        'view crm organizations', 'edit crm organizations', 'edit crm people',
    ]);

    $organization = Organization::create(['name' => 'Legacy org']);
    $other = Organization::create(['name' => 'Someone else']);
    $person = Person::create(['first_name' => 'Foreign', 'organization_id' => $other->id]);

    Livewire::test(AuthzLiveRelatedPerson::class, ['model' => $organization])
        ->call('remove', $person->id)
        ->assertOk();

    expect((int) $person->fresh()->organization_id)->toBe($other->id);
});

/*
 * ---------------------------------------------------------------------------
 * Document action -- PayInvoice::pay.
 * ---------------------------------------------------------------------------
 */

it('forbids paying an invoice without the invoice edit permission', function () {
    $this->actingAsUserWithPermissions(['view crm invoices']);

    $invoice = Invoice::create(['total' => 10000, 'amount_due' => 10000, 'amount_paid' => 0]);

    Livewire::test(AuthzPayInvoice::class, ['invoice' => $invoice])
        ->call('pay')
        ->assertForbidden();

    expect((int) $invoice->fresh()->amount_paid)->toBe(0)
        ->and($invoice->fresh()->fully_paid_at)->toBeNull();
});

/*
 * ---------------------------------------------------------------------------
 * Kanban board -- LiveDealBoard::onStageChanged, which bulk-updates records straight
 * from a browser-supplied id array.
 * ---------------------------------------------------------------------------
 */

it('forbids moving a deal between board stages without the deal edit permission', function () {
    $this->actingAsUserWithPermissions(['view crm deals']);

    $pipeline = Pipeline::create(['name' => 'Deals', 'model' => Deal::class]);
    $stage = PipelineStage::create([
        'pipeline_id' => $pipeline->id,
        'name' => 'Target stage',
        'order' => 1,
    ]);
    $deal = Deal::create(['title' => 'Legacy deal', 'pipeline_stage_order' => 1]);

    Livewire::test(AuthzLiveDealBoard::class)
        ->call('onStageChanged', $deal->id, $stage->id, [], [$deal->id])
        ->assertForbidden();

    expect($deal->fresh()->pipeline_stage_id)->toBeNull();
});

it('allows moving a deal between board stages with the deal edit permission', function () {
    $this->actingAsUserWithPermissions(['view crm deals', 'edit crm deals']);

    $pipeline = Pipeline::create(['name' => 'Deals', 'model' => Deal::class]);
    $stage = PipelineStage::create([
        'pipeline_id' => $pipeline->id,
        'name' => 'Target stage',
        'order' => 1,
    ]);
    $deal = Deal::create(['title' => 'Legacy deal', 'pipeline_stage_order' => 1]);

    Livewire::test(AuthzLiveDealBoard::class)
        ->call('onStageChanged', $deal->id, $stage->id, [], [$deal->id])
        ->assertOk();

    expect((int) $deal->fresh()->pipeline_stage_id)->toBe($stage->id);
});

/*
 * ---------------------------------------------------------------------------
 * Custom fields -- Fields\CreateOrEdit::submit branches on whether $field is bound,
 * so each branch needs its own ability.
 * ---------------------------------------------------------------------------
 */

it('forbids creating a custom field without the create permission', function () {
    $this->actingAsUserWithPermissions(['view crm fields']);
    $before = Field::count();

    Livewire::test(AuthzFieldCreateOrEdit::class)
        ->set('fieldType', 'text')
        ->set('fieldName', 'Denied field')
        ->call('submit')
        ->assertForbidden();

    expect(Field::count())->toBe($before);
});

it('allows creating a custom field with the create permission', function () {
    $this->actingAsUserWithPermissions(['view crm fields', 'create crm fields']);

    Livewire::test(AuthzFieldCreateOrEdit::class)
        ->set('fieldType', 'text')
        ->set('fieldName', 'Allowed field')
        ->call('submit')
        ->assertOk();

    expect(Field::where('name', 'Allowed field')->exists())->toBeTrue();
});

it('forbids updating a custom field without the edit permission and leaves it intact', function () {
    $this->actingAsUserWithPermissions(['view crm fields']);

    $field = Field::create(['type' => 'text', 'name' => 'Original field']);

    Livewire::test(AuthzFieldCreateOrEdit::class, ['field' => $field])
        ->set('fieldName', 'Tampered field')
        ->call('submit')
        ->assertForbidden();

    expect($field->fresh()->name)->toBe('Original field');
});

it('allows updating a custom field with the edit permission', function () {
    $this->actingAsUserWithPermissions(['view crm fields', 'edit crm fields']);

    $field = Field::create(['type' => 'text', 'name' => 'Original field']);

    Livewire::test(AuthzFieldCreateOrEdit::class, ['field' => $field])
        ->set('fieldName', 'Renamed field')
        ->call('submit')
        ->assertOk();

    expect($field->fresh()->name)->toBe('Renamed field');
});
