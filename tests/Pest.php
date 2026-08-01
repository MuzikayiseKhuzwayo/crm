<?php

use Illuminate\Support\Str;
use VentureDrake\LaravelCrm\Models\AddressType;
use VentureDrake\LaravelCrm\Models\Deal;
use VentureDrake\LaravelCrm\Models\Delivery;
use VentureDrake\LaravelCrm\Models\Invoice;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Order;
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
