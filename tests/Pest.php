<?php

use Illuminate\Support\Str;
use Livewire\Livewire;
use VentureDrake\LaravelCrm\Models\AddressType;
use VentureDrake\LaravelCrm\Models\Deal;
use VentureDrake\LaravelCrm\Models\Delivery;
use VentureDrake\LaravelCrm\Models\Invoice;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Order;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Models\Pipeline;
use VentureDrake\LaravelCrm\Models\PurchaseOrder;
use VentureDrake\LaravelCrm\Models\Quote;
use VentureDrake\LaravelCrm\Models\Setting;
use VentureDrake\LaravelCrm\Tests\TestCase;
use VentureDrake\LaravelCrm\Tests\V1TestCase;

uses(TestCase::class)->in('Feature', 'Unit');
uses(V1TestCase::class)->in('Upgrade');

/*
 * The authorization suite mounts real CRM Livewire components. Their mount() methods
 * read a handful of baseline records (default currency, the per-model pipeline and its
 * stages, the standard address types) that the minimal TestSchema does not seed. Seed
 * them here so a mount failure can never be mistaken for an authorization failure.
 */
uses()->beforeEach(function () {
    Setting::updateOrCreate(['name' => 'currency'], ['value' => 'USD']);
    // The 'team' setting row is the polymorphic anchor the CRM hangs its own
    // phones/emails/addresses off; several mountCommon() helpers dereference it.
    Setting::updateOrCreate(['name' => 'team'], ['value' => 'related']);

    foreach ([Lead::class, Deal::class, Quote::class, Order::class, Invoice::class, PurchaseOrder::class, Delivery::class] as $model) {
        $pipeline = Pipeline::create([
            'external_id' => Str::uuid()->toString(),
            'name' => class_basename($model).' Pipeline',
            'model' => $model,
        ]);

        foreach (['Pending', 'Draft', 'Accepted', 'Rejected', 'Closed Won', 'Closed Lost'] as $order => $stage) {
            $pipeline->pipelineStages()->create([
                'external_id' => Str::uuid()->toString(),
                'name' => $stage,
                'order' => $order,
            ]);
        }
    }

    foreach (['Billing', 'Shipping'] as $type) {
        AddressType::firstOrCreate(['name' => $type]);
    }
})->in('Feature/Livewire/Authorization');

/**
 * Shared generator for the Call/Meeting/Lunch authorization suites.
 *
 * These three sub-item components are structurally identical -- the same update/delete
 * guard on the *Item component and the same create guard on the *Related component --
 * so the deny/allow pairs are generated once here rather than triplicated by hand.
 *
 * @param  class-string  $model  e.g. Call::class
 * @param  class-string  $itemComponent  render-stubbed *Item subclass
 * @param  class-string  $relatedComponent  render-stubbed *Related subclass
 * @param  string  $property  the *Item component's model property, e.g. 'call'
 * @param  string  $relation  the parent's has-many relation, e.g. 'calls'
 * @param  string  $morph  the polymorphic prefix, e.g. 'callable'
 * @param  string  $permissionSuffix  e.g. 'crm calls'
 * @param  string  $label  human label used in the test descriptions
 */
function itGuardsAnActivityItem(
    string $model,
    string $itemComponent,
    string $relatedComponent,
    string $property,
    string $relation,
    string $morph,
    string $permissionSuffix,
    string $label,
): void {
    $make = function () use ($model, $morph) {
        $parent = Person::create(['first_name' => 'Authz Parent']);

        return [$parent, $model::create([
            'name' => 'Original name',
            'start_at' => now()->toDateTimeString(),
            'finish_at' => now()->addHour()->toDateTimeString(),
            $morph.'_type' => $parent->getMorphClass(),
            $morph.'_id' => $parent->id,
        ])];
    };

    it("forbids updating a {$label} without the edit permission and leaves the record intact", function () use ($make, $itemComponent, $property, $permissionSuffix) {
        $this->actingAsUserWithPermissions(["view {$permissionSuffix}"]);
        [, $record] = $make();

        Livewire::test($itemComponent, [$property => $record])
            ->set('name', 'Tampered')
            ->call('update')
            ->assertForbidden();

        expect($record->fresh()->name)->toBe('Original name');
    });

    it("allows updating a {$label} with the edit permission", function () use ($make, $itemComponent, $property, $permissionSuffix) {
        $this->actingAsUserWithPermissions(["view {$permissionSuffix}", "edit {$permissionSuffix}"]);
        [, $record] = $make();

        Livewire::test($itemComponent, [$property => $record])
            ->set('name', 'Updated name')
            ->call('update')
            ->assertOk();

        expect($record->fresh()->name)->toBe('Updated name');
    });

    it("forbids deleting a {$label} without the delete permission and leaves the record intact", function () use ($make, $model, $itemComponent, $property, $permissionSuffix) {
        $this->actingAsUserWithPermissions(["view {$permissionSuffix}"]);
        [, $record] = $make();

        Livewire::test($itemComponent, [$property => $record])
            ->call('delete')
            ->assertForbidden();

        expect($model::find($record->id))->not->toBeNull();
    });

    it("allows deleting a {$label} with the delete permission", function () use ($make, $model, $itemComponent, $property, $permissionSuffix) {
        $this->actingAsUserWithPermissions(["view {$permissionSuffix}", "delete {$permissionSuffix}"]);
        [, $record] = $make();

        Livewire::test($itemComponent, [$property => $record])
            ->call('delete')
            ->assertOk();

        expect($model::find($record->id))->toBeNull();
    });

    it("forbids adding a related {$label} without the create permission and creates no record", function () use ($model, $relatedComponent, $permissionSuffix) {
        $this->actingAsUserWithPermissions(["view {$permissionSuffix}"]);
        $parent = Person::create(['first_name' => 'Authz Parent']);
        $before = $model::count();

        Livewire::test($relatedComponent, ['model' => $parent])
            ->set('name', 'Related record')
            ->set('start_at', now()->toDateTimeString())
            ->set('finish_at', now()->addHour()->toDateTimeString())
            ->call('save')
            ->assertForbidden();

        expect($model::count())->toBe($before);
    });

    it("allows adding a related {$label} with the create permission", function () use ($relatedComponent, $relation, $permissionSuffix) {
        $this->actingAsUserWithPermissions(["view {$permissionSuffix}", "create {$permissionSuffix}"]);
        $parent = Person::create(['first_name' => 'Authz Parent']);

        Livewire::test($relatedComponent, ['model' => $parent])
            ->set('name', 'Related record')
            ->set('start_at', now()->toDateTimeString())
            ->set('finish_at', now()->addHour()->toDateTimeString())
            ->call('save')
            ->assertOk();

        expect($parent->{$relation}()->count())->toBe(1);
    });
}
