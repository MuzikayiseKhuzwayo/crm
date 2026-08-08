<?php

/*
 * Quote::orderComplete(), Order::invoiceComplete() and
 * Order::deliveryComplete() decide whether a document has been drawn down in
 * full.
 *
 * They subtract the child quantities from the parent and used to test the
 * remainder with `> 0`. With integer quantities that was exact; with
 * decimal(15,3) it is not - 1.1 less 0.7 less 0.4 leaves 1.11e-16, which is
 * greater than zero, so the document would read as outstanding forever.
 */

use VentureDrake\LaravelCrm\Models\Delivery;
use VentureDrake\LaravelCrm\Models\Invoice;
use VentureDrake\LaravelCrm\Models\Order;
use VentureDrake\LaravelCrm\Models\Quote;

test('an order fully invoiced across two parts is complete despite float dust', function () {
    $order = Order::create([]);
    $orderProduct = $order->orderProducts()->create(['quantity' => 1.1]);

    foreach ([0.7, 0.4] as $part) {
        $invoice = Invoice::create(['order_id' => $order->id]);
        $invoice->invoiceLines()->create([
            'order_product_id' => $orderProduct->id,
            'quantity' => $part,
        ]);
    }

    expect($order->fresh()->invoiceComplete())->toBeTrue();
});

test('an order part invoiced is not complete', function () {
    $order = Order::create([]);
    $orderProduct = $order->orderProducts()->create(['quantity' => 3.5]);

    $invoice = Invoice::create(['order_id' => $order->id]);
    $invoice->invoiceLines()->create([
        'order_product_id' => $orderProduct->id,
        'quantity' => 1.25,
    ]);

    expect($order->fresh()->invoiceComplete())->toBeFalse();
});

test('an order fully delivered across two parts is complete despite float dust', function () {
    $order = Order::create([]);
    $orderProduct = $order->orderProducts()->create(['quantity' => 1.1]);

    foreach ([0.7, 0.4] as $part) {
        $delivery = Delivery::create(['order_id' => $order->id]);
        $delivery->deliveryProducts()->create([
            'order_product_id' => $orderProduct->id,
            'quantity' => $part,
        ]);
    }

    expect($order->fresh()->deliveryComplete())->toBeTrue();
});

test('an order part delivered is not complete', function () {
    $order = Order::create([]);
    $orderProduct = $order->orderProducts()->create(['quantity' => 3.5]);

    $delivery = Delivery::create(['order_id' => $order->id]);
    $delivery->deliveryProducts()->create([
        'order_product_id' => $orderProduct->id,
        'quantity' => 1.25,
    ]);

    expect($order->fresh()->deliveryComplete())->toBeFalse();
});

test('a quote fully ordered across two parts is complete despite float dust', function () {
    $quote = Quote::create(['title' => 'Q']);
    $quoteProduct = $quote->quoteProducts()->create(['quantity' => 1.1]);

    foreach ([0.7, 0.4] as $part) {
        $order = Order::create(['quote_id' => $quote->id]);
        $order->orderProducts()->create([
            'quote_product_id' => $quoteProduct->id,
            'quantity' => $part,
        ]);
    }

    expect($quote->fresh()->orderComplete())->toBeTrue();
});

test('a quote part ordered is not complete', function () {
    $quote = Quote::create(['title' => 'Q']);
    $quoteProduct = $quote->quoteProducts()->create(['quantity' => 3.5]);

    $order = Order::create(['quote_id' => $quote->id]);
    $order->orderProducts()->create([
        'quote_product_id' => $quoteProduct->id,
        'quantity' => 1.25,
    ]);

    expect($quote->fresh()->orderComplete())->toBeFalse();
});
