<?php

use Livewire\Livewire;
use VentureDrake\LaravelCrm\Livewire\RelatedDeals;
use VentureDrake\LaravelCrm\Livewire\RelatedOrganizations;
use VentureDrake\LaravelCrm\Livewire\RelatedPeople;
use VentureDrake\LaravelCrm\Models\Organization;
use VentureDrake\LaravelCrm\Models\Person;

/**
 * Render-stub subclasses.
 *
 * Livewire renders a component on mount, and the related-contact blades reach for
 * tables the minimal TestSchema does not ship. Overriding only render() leaves the real
 * action methods -- and the $this->authorize() guards inside them -- completely intact,
 * so these tests still exercise the production authorization path against the real
 * policies.
 */
class AuthzRelatedPeople extends RelatedPeople
{
    public function render()
    {
        return '<div></div>';
    }
}
class AuthzRelatedOrganizations extends RelatedOrganizations
{
    public function render()
    {
        return '<div></div>';
    }
}
class AuthzRelatedDeals extends RelatedDeals
{
    public function render()
    {
        return '<div></div>';
    }
}

/*
 * The parent record is deliberately the *other* entity type in each suite so the two
 * guards discriminate cleanly: `update` is checked against the parent, `create` against
 * the newly-minted contact.
 */

it('forbids adding a related person without the update permission on the parent', function () {
    $this->actingAsUserWithPermissions(['view crm organizations', 'view crm people', 'create crm people']);
    $parent = Organization::create(['name' => 'Authz Org']);

    Livewire::test(AuthzRelatedPeople::class, ['model' => $parent])
        ->set('person_name', 'New Contact')
        ->call('add')
        ->assertForbidden();

    expect($parent->contacts()->count())->toBe(0);
});

it('forbids minting a brand new person from the related panel without the create permission', function () {
    // Holds `edit` on the parent -- so the first guard passes -- but not `create` on Person.
    $this->actingAsUserWithPermissions(['view crm organizations', 'edit crm organizations', 'view crm people']);
    $parent = Organization::create(['name' => 'Authz Org']);
    $before = Person::count();

    Livewire::test(AuthzRelatedPeople::class, ['model' => $parent])
        ->set('person_name', 'New Contact')
        ->call('add')
        ->assertForbidden();

    expect(Person::count())->toBe($before)
        ->and($parent->contacts()->count())->toBe(0);
});

it('allows adding a related person with update on the parent and create on the person', function () {
    $this->actingAsUserWithPermissions([
        'view crm organizations', 'edit crm organizations',
        'view crm people', 'create crm people',
    ]);
    $parent = Organization::create(['name' => 'Authz Org']);

    Livewire::test(AuthzRelatedPeople::class, ['model' => $parent])
        ->set('person_name', 'New Contact')
        ->call('add')
        ->assertOk();

    expect($parent->contacts()->count())->toBe(1)
        ->and(Person::where('first_name', 'New')->exists())->toBeTrue();
});

it('forbids removing a related person without the update permission on the parent', function () {
    $this->actingAsUserWithPermissions(['view crm organizations', 'view crm people', 'delete crm people']);
    $parent = Organization::create(['name' => 'Authz Org']);
    $person = Person::create(['first_name' => 'Linked']);
    $parent->contacts()->create([
        'entityable_type' => $person->getMorphClass(),
        'entityable_id' => $person->id,
    ]);

    Livewire::test(AuthzRelatedPeople::class, ['model' => $parent])
        ->call('remove', $person->id)
        ->assertForbidden();

    expect($parent->contacts()->count())->toBe(1)
        ->and(Person::find($person->id))->not->toBeNull();
});

it('allows removing a related person with the update permission on the parent', function () {
    $this->actingAsUserWithPermissions(['view crm organizations', 'edit crm organizations', 'view crm people']);
    $parent = Organization::create(['name' => 'Authz Org']);
    $person = Person::create(['first_name' => 'Linked']);
    $parent->contacts()->create([
        'entityable_type' => $person->getMorphClass(),
        'entityable_id' => $person->id,
    ]);

    Livewire::test(AuthzRelatedPeople::class, ['model' => $parent])
        ->call('remove', $person->id)
        ->assertOk();

    // The pivot row is detached; the Person itself is deliberately left alone.
    expect($parent->contacts()->count())->toBe(0)
        ->and(Person::find($person->id))->not->toBeNull();
});

it('forbids adding a related organization without the update permission on the parent', function () {
    $this->actingAsUserWithPermissions(['view crm people', 'view crm organizations', 'create crm organizations']);
    $parent = Person::create(['first_name' => 'Authz Parent']);

    Livewire::test(AuthzRelatedOrganizations::class, ['model' => $parent])
        ->set('organization_name', 'New Org')
        ->call('add')
        ->assertForbidden();

    expect($parent->contacts()->count())->toBe(0);
});

it('forbids minting a brand new organization from the related panel without the create permission', function () {
    $this->actingAsUserWithPermissions(['view crm people', 'edit crm people', 'view crm organizations']);
    $parent = Person::create(['first_name' => 'Authz Parent']);
    $before = Organization::count();

    Livewire::test(AuthzRelatedOrganizations::class, ['model' => $parent])
        ->set('organization_name', 'New Org')
        ->call('add')
        ->assertForbidden();

    expect(Organization::count())->toBe($before)
        ->and($parent->contacts()->count())->toBe(0);
});

it('allows adding a related organization with update on the parent and create on the organization', function () {
    $this->actingAsUserWithPermissions([
        'view crm people', 'edit crm people',
        'view crm organizations', 'create crm organizations',
    ]);
    $parent = Person::create(['first_name' => 'Authz Parent']);

    Livewire::test(AuthzRelatedOrganizations::class, ['model' => $parent])
        ->set('organization_name', 'New Org')
        ->call('add')
        ->assertOk();

    expect($parent->contacts()->count())->toBe(1)
        ->and(Organization::where('name', 'New Org')->exists())->toBeTrue();
});

it('forbids removing a related organization without the update permission on the parent', function () {
    $this->actingAsUserWithPermissions(['view crm people', 'view crm organizations', 'delete crm organizations']);
    $parent = Person::create(['first_name' => 'Authz Parent']);
    $organization = Organization::create(['name' => 'Linked Org']);
    $parent->contacts()->create([
        'entityable_type' => $organization->getMorphClass(),
        'entityable_id' => $organization->id,
    ]);

    Livewire::test(AuthzRelatedOrganizations::class, ['model' => $parent])
        ->call('remove', $organization->id)
        ->assertForbidden();

    expect($parent->contacts()->count())->toBe(1)
        ->and(Organization::find($organization->id))->not->toBeNull();
});

it('allows removing a related organization with the update permission on the parent', function () {
    $this->actingAsUserWithPermissions(['view crm people', 'edit crm people', 'view crm organizations']);
    $parent = Person::create(['first_name' => 'Authz Parent']);
    $organization = Organization::create(['name' => 'Linked Org']);
    $parent->contacts()->create([
        'entityable_type' => $organization->getMorphClass(),
        'entityable_id' => $organization->id,
    ]);

    Livewire::test(AuthzRelatedOrganizations::class, ['model' => $parent])
        ->call('remove', $organization->id)
        ->assertOk();

    expect($parent->contacts()->count())->toBe(0)
        ->and(Organization::find($organization->id))->not->toBeNull();
});

it('leaves the in-memory RelatedDeals add and remove unguarded', function () {
    // RelatedDeals only mutates an in-memory array, so it deliberately carries no guard.
    $this->actingAsUserWithPermissions([]);
    $parent = Person::create(['first_name' => 'Authz Parent']);

    Livewire::test(AuthzRelatedDeals::class, ['model' => $parent, 'data' => ['a', 'b']])
        ->call('add')
        ->assertOk()
        ->call('remove', 0)
        ->assertOk()
        ->assertSet('data', [1 => 'b']);

    // Asserted against the source rather than the trait list: Livewire's own base
    // Component already uses AuthorizesRequests, so every component inherits the
    // authorize() helper. What matters here is that RelatedDeals never calls it.
    expect(file_get_contents((new ReflectionClass(RelatedDeals::class))->getFileName()))
        ->not->toContain('$this->authorize(');
});
