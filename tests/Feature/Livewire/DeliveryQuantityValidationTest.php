<?php

/*
 * The delivery form has never run validate() at all - the quantity was picked
 * from a `<select>` built by an integer loop over the order's outstanding
 * amount, so an over-delivery was simply unreachable from the browser.
 *
 * Decimal quantities cannot come from a dropdown, so the cap now has to be
 * enforced on submit.
 */

use Illuminate\Support\Str;
use Livewire\Livewire;
use VentureDrake\LaravelCrm\Livewire\Deliveries\DeliveryCreate;
use VentureDrake\LaravelCrm\Models\Delivery;
use VentureDrake\LaravelCrm\Models\DeliveryProduct;
use VentureDrake\LaravelCrm\Models\Invoice;
use VentureDrake\LaravelCrm\Models\Order;
use VentureDrake\LaravelCrm\Models\Pipeline;
use VentureDrake\LaravelCrm\Models\Product;
use VentureDrake\LaravelCrm\Models\Setting;

/*
 * The delivery blade reaches for tables the minimal TestSchema does not ship,
 * so the component is mounted through a render stub - the same discipline as
 * the Authorization and PdfTemplateSelect suites. mount() and save() still
 * run for real.
 */
class QtyDeliveryCreate extends DeliveryCreate
{
    public function render()
    {
        return '<div></div>';
    }
}

beforeEach(function () {
    $this->actingAsUserWithPermissions([
        'view crm deliveries', 'create crm deliveries', 'edit crm deliveries',
    ]);

    Setting::updateOrCreate(['name' => 'currency'], ['value' => 'USD']);
    Setting::updateOrCreate(['name' => 'team'], ['value' => 'related']);

    // HasDeliveryCommon::mountCommon() looks its pipeline up by Invoice.
    $pipeline = Pipeline::create([
        'external_id' => Str::uuid()->toString(),
        'name' => 'Invoice Pipeline',
        'model' => Invoice::class,
    ]);

    $pipeline->pipelineStages()->create([
        'external_id' => Str::uuid()->toString(),
        'name' => 'Draft',
        'order' => 0,
    ]);

    app('laravel-crm.settings')->forgetCache();
});

function orderReadyToDeliver(): array
{
    $product = Product::create(['name' => 'By weight']);

    $order = Order::create(['currency' => 'USD']);
    $orderProduct = $order->orderProducts()->create([
        'product_id' => $product->id, 'quantity' => 3.5, 'price' => 1000, 'amount' => 35000,
    ]);

    return [$order, $orderProduct];
}

test('a delivery cannot exceed the order outstanding quantity', function () {
    [$order, $orderProduct] = orderReadyToDeliver();

    $component = Livewire::test(QtyDeliveryCreate::class, [
        'fromModelType' => 'order', 'fromModelId' => $order->id,
    ]);

    $component->set('products', [[
        'order_product_id' => $orderProduct->id,
        'quantity' => 5,
        'quantity_max' => 3.5,
    ]]);

    $component->call('save')->assertHasErrors('products.0.quantity');

    expect(Delivery::count())->toBe(0)
        ->and(DeliveryProduct::count())->toBe(0);
});

test('a delivery within the order outstanding quantity is accepted', function () {
    [$order, $orderProduct] = orderReadyToDeliver();

    $component = Livewire::test(QtyDeliveryCreate::class, [
        'fromModelType' => 'order', 'fromModelId' => $order->id,
    ]);

    $component->set('products', [[
        'order_product_id' => $orderProduct->id,
        'quantity' => 2.5,
        'quantity_max' => 3.5,
    ]]);

    $component->call('save')->assertHasNoErrors();

    expect(DeliveryProduct::first()->quantity)->toBe(2.5);
});

test('a delivery quantity finer than 3 decimal places is rejected', function () {
    [$order, $orderProduct] = orderReadyToDeliver();

    $component = Livewire::test(QtyDeliveryCreate::class, [
        'fromModelType' => 'order', 'fromModelId' => $order->id,
    ]);

    $component->set('products', [[
        'order_product_id' => $orderProduct->id,
        'quantity' => 1.2345,
        'quantity_max' => 3.5,
    ]]);

    $component->call('save')->assertHasErrors('products.0.quantity');
});

/*
 * `quantity_max` is a public Livewire property, so it round-trips through the
 * browser and comes back with whatever the caller put in it. The cap is taken
 * from the order line instead.
 */

test('a raised quantity_max does not buy a larger delivery', function () {
    [$order, $orderProduct] = orderReadyToDeliver();

    $component = Livewire::test(QtyDeliveryCreate::class, [
        'fromModelType' => 'order', 'fromModelId' => $order->id,
    ]);

    $component->set('products', [[
        'order_product_id' => $orderProduct->id,
        'quantity' => 5,
        'quantity_max' => 9999,
    ]]);

    $component->call('save')->assertHasErrors('products.0.quantity');

    expect(DeliveryProduct::count())->toBe(0);
});

test('a missing quantity_max does not lift the cap', function () {
    [$order, $orderProduct] = orderReadyToDeliver();

    $component = Livewire::test(QtyDeliveryCreate::class, [
        'fromModelType' => 'order', 'fromModelId' => $order->id,
    ]);

    $component->set('products', [[
        'order_product_id' => $orderProduct->id,
        'quantity' => 5,
    ]]);

    $component->call('save')->assertHasErrors('products.0.quantity');

    expect(DeliveryProduct::count())->toBe(0);
});

test('a delivery already raised comes off the remaining quantity', function () {
    [$order, $orderProduct] = orderReadyToDeliver();

    $delivered = Delivery::create(['order_id' => $order->id]);
    $delivered->deliveryProducts()->create([
        'order_product_id' => $orderProduct->id,
        'quantity' => 3,
    ]);

    $component = Livewire::test(QtyDeliveryCreate::class, [
        'fromModelType' => 'order', 'fromModelId' => $order->id,
    ]);

    // 0.5 of the 3.5 is left, whatever the row claims.
    $component->set('products', [[
        'order_product_id' => $orderProduct->id,
        'quantity' => 1,
        'quantity_max' => 3.5,
    ]]);

    $component->call('save')->assertHasErrors('products.0.quantity');

    $component->set('products.0.quantity', 0.5);

    $component->call('save')->assertHasNoErrors();

    expect(DeliveryProduct::count())->toBe(2);
});

test('a line on a deleted delivery gives its quantity back', function () {
    [$order, $orderProduct] = orderReadyToDeliver();

    $delivered = Delivery::create(['order_id' => $order->id]);
    $delivered->deliveryProducts()->create([
        'order_product_id' => $orderProduct->id,
        'quantity' => 3,
    ]);

    $delivered->delete();

    $component = Livewire::test(QtyDeliveryCreate::class, [
        'fromModelType' => 'order', 'fromModelId' => $order->id,
    ]);

    $component->set('products', [[
        'order_product_id' => $orderProduct->id,
        'quantity' => 3.5,
    ]]);

    $component->call('save')->assertHasNoErrors();
});
