<?php

/*
 * subTotal() and total() reconcile a document's stored header against its
 * lines. Both fed the show pages and the index badges long before quantities
 * went decimal, and with integer quantities `quantity * price` was always a
 * whole number of cents so the sum was exact either way.
 *
 * It is not exact any more. The form rounds each line to the cent before
 * summing them into the header, so the check has to round per line too -
 * summing the raw products and rounding once at the end accumulates the
 * half-cent remainders and puts a mismatch icon on a document whose numbers
 * are perfectly consistent.
 */

use VentureDrake\LaravelCrm\Models\Order;
use VentureDrake\LaravelCrm\Models\Product;
use VentureDrake\LaravelCrm\Models\Quote;

use function VentureDrake\LaravelCrm\Http\Helpers\CheckAmount\subTotal;
use function VentureDrake\LaravelCrm\Http\Helpers\CheckAmount\total;

/**
 * Two lines of 0.5 at $9.99. Each line computes 499.5 cents and stores 500,
 * so the header is 1000 - the shape that breaks a sum-then-round check.
 */
function quoteWithHalfCentLines(): Quote
{
    $product = Product::create(['name' => 'By weight']);

    $quote = Quote::create([
        'title' => 'Q', 'currency' => 'USD',
        'subtotal' => 10.00, 'discount' => 0, 'tax' => 0, 'adjustments' => 0, 'total' => 10.00,
    ]);

    foreach (range(1, 2) as $ignored) {
        $quote->quoteProducts()->create([
            'product_id' => $product->id, 'quantity' => 0.5, 'price' => 9.99,
            'tax_rate' => 0, 'tax_amount' => 0, 'amount' => 5.00,
        ]);
    }

    return $quote->fresh();
}

test('the stored header really is the sum of the rounded lines', function () {
    $quote = quoteWithHalfCentLines();

    expect($quote->quoteProducts->pluck('amount')->all())->toBe([500, 500])
        ->and($quote->subtotal)->toBe(1000);
});

test('a quote of fractional lines reconciles', function () {
    $quote = quoteWithHalfCentLines();

    expect(subTotal($quote))->toBeTrue()
        ->and(total($quote))->toBeTrue();
});

test('an order of fractional lines reconciles', function () {
    $product = Product::create(['name' => 'By weight']);

    $order = Order::create([
        'currency' => 'USD',
        'subtotal' => 10.00, 'discount' => 0, 'tax' => 0, 'adjustments' => 0, 'total' => 10.00,
    ]);

    foreach (range(1, 2) as $ignored) {
        $order->orderProducts()->create([
            'product_id' => $product->id, 'quantity' => 0.5, 'price' => 9.99,
            'tax_rate' => 0, 'tax_amount' => 0, 'amount' => 5.00,
        ]);
    }

    $order = $order->fresh();

    expect(subTotal($order))->toBeTrue()
        ->and(total($order))->toBeTrue();
});

test('a genuinely wrong header is still reported', function () {
    $product = Product::create(['name' => 'By weight']);

    $quote = Quote::create([
        'title' => 'Q', 'currency' => 'USD',
        'subtotal' => 99.00, 'discount' => 0, 'tax' => 0, 'adjustments' => 0, 'total' => 99.00,
    ]);

    $quote->quoteProducts()->create([
        'product_id' => $product->id, 'quantity' => 0.5, 'price' => 9.99,
        'tax_rate' => 0, 'tax_amount' => 0, 'amount' => 5.00,
    ]);

    $quote = $quote->fresh();

    expect(subTotal($quote))->toBeFalse()
        ->and(total($quote))->toBeFalse();
});
