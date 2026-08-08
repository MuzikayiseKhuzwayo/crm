<?php

/*
 * Line item quantity is decimal(15,3) so a product can be sold by weight or
 * volume. HasDecimalQuantity rounds on write and casts to float on read, so
 * a quantity round-trips as a number rather than as the "3.500" string a
 * decimal column returns on MySQL and Postgres.
 */

use VentureDrake\LaravelCrm\Models\Deal;
use VentureDrake\LaravelCrm\Models\DealProduct;
use VentureDrake\LaravelCrm\Models\Delivery;
use VentureDrake\LaravelCrm\Models\DeliveryProduct;
use VentureDrake\LaravelCrm\Models\Invoice;
use VentureDrake\LaravelCrm\Models\InvoiceLine;
use VentureDrake\LaravelCrm\Models\Order;
use VentureDrake\LaravelCrm\Models\OrderProduct;
use VentureDrake\LaravelCrm\Models\PurchaseOrder;
use VentureDrake\LaravelCrm\Models\PurchaseOrderLine;
use VentureDrake\LaravelCrm\Models\Quote;
use VentureDrake\LaravelCrm\Models\QuoteProduct;

/**
 * Every line item table has a NOT NULL parent id, so each row needs its
 * document creating first.
 */
function lineItemWithQuantity(string $model, $quantity)
{
    $parent = match ($model) {
        QuoteProduct::class => ['quote_id' => Quote::create(['title' => 'Q'])->id],
        OrderProduct::class => ['order_id' => Order::create([])->id],
        DealProduct::class => ['deal_id' => Deal::create(['title' => 'D'])->id],
        InvoiceLine::class => ['invoice_id' => Invoice::create([])->id],
        PurchaseOrderLine::class => ['purchase_order_id' => PurchaseOrder::create([])->id],
        DeliveryProduct::class => ['delivery_id' => Delivery::create([])->id],
    };

    return $model::create($parent + ['quantity' => $quantity]);
}

dataset('lineItemModels', [
    'quote product' => QuoteProduct::class,
    'order product' => OrderProduct::class,
    'deal product' => DealProduct::class,
    'invoice line' => InvoiceLine::class,
    'purchase order line' => PurchaseOrderLine::class,
    'delivery product' => DeliveryProduct::class,
]);

test('a fractional quantity stores and reads back as a float', function (string $model) {
    expect(lineItemWithQuantity($model, 3.5)->fresh()->quantity)->toBe(3.5);
})->with('lineItemModels');

test('a whole quantity reads back as a float, not as 2.000', function (string $model) {
    expect(lineItemWithQuantity($model, 2)->fresh()->quantity)->toBe(2.0);
})->with('lineItemModels');

test('a quantity finer than 3 decimal places is rounded on write', function (string $model) {
    expect(lineItemWithQuantity($model, 3.5555)->fresh()->quantity)->toBe(3.556);
})->with('lineItemModels');

test('a null quantity stays null rather than becoming zero', function (string $model) {
    // LaravelCrmUpdate backfills deliveryProducts()->whereNull('quantity'),
    // so null has to stay distinguishable from 0.
    expect(lineItemWithQuantity($model, null)->fresh()->quantity)->toBeNull();
})->with('lineItemModels');

test('a blank quantity is stored as null, not as zero', function (string $model) {
    expect(lineItemWithQuantity($model, '')->fresh()->quantity)->toBeNull();
})->with('lineItemModels');

test('the delivery pdf filter on quantity greater than zero still works', function () {
    // The delivery PDF templates filter their lines with
    // ->where('quantity', '>', 0), which now runs against the float accessor.
    $delivered = lineItemWithQuantity(DeliveryProduct::class, 0.5);
    lineItemWithQuantity(DeliveryProduct::class, 0);
    lineItemWithQuantity(DeliveryProduct::class, null);

    $kept = DeliveryProduct::all()->where('quantity', '>', 0);

    expect($kept)->toHaveCount(1)
        ->and($kept->first()->id)->toBe($delivered->id);
});
