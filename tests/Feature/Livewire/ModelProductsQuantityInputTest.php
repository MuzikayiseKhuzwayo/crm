<?php

/*
 * The quantity field on the shared product form.
 *
 * Blade compiles component tags before directives, so an `@if` sitting between
 * a tag's attributes stops the tag matching at all and `<x-mary-input …/>`
 * survives into the response as literal text - an unknown element the browser
 * renders as nothing. The remaining-quantity cap therefore has to be bound
 * (`:max`, `:hint`) rather than wrapped in a conditional.
 */

use Livewire\Livewire;
use VentureDrake\LaravelCrm\Livewire\ModelProducts;
use VentureDrake\LaravelCrm\Models\Order;
use VentureDrake\LaravelCrm\Models\Product;
use VentureDrake\LaravelCrm\Models\Quote;

function quoteWithOneLine(): Quote
{
    $product = Product::create(['name' => 'A']);

    $quote = Quote::create(['title' => 'Q', 'currency' => 'USD']);
    $quote->quoteProducts()->create([
        'product_id' => $product->id, 'quantity' => 3, 'price' => 4500,
        'tax_rate' => 10, 'tax_amount' => 135000, 'amount' => 13500,
    ]);

    return $quote;
}

test('the quantity field compiles to a real input', function () {
    $this->actingAsUser();

    $html = Livewire::test(ModelProducts::class, ['model' => quoteWithOneLine()])->html();

    expect($html)->not->toContain('<x-mary-')
        ->and($html)->toContain('wire:model.live.debounce.500ms="products.0.quantity"')
        ->and($html)->toContain('step="0.001"');
});

test('an uncapped line carries no max and no remaining hint', function () {
    $this->actingAsUser();

    $html = Livewire::test(ModelProducts::class, ['model' => quoteWithOneLine()])->html();

    expect($html)->not->toContain('max=')
        ->and($html)->not->toContain('Remaining');
});

test('a line drawing down an order shows its remaining quantity', function () {
    $this->actingAsUser();

    $product = Product::create(['name' => 'By weight']);

    $order = Order::create(['currency' => 'USD']);
    $order->orderProducts()->create([
        'product_id' => $product->id, 'quantity' => 3.5, 'price' => 1000, 'amount' => 35000,
    ]);

    $html = Livewire::test(ModelProducts::class, [
        'model' => $order, 'creating' => 'Invoice', 'from' => 'Order',
    ])->html();

    // Formatted, not raw: the column hands back "3.500" on MySQL and Postgres.
    expect($html)->not->toContain('<x-mary-')
        ->and($html)->toContain('max="3.5"')
        ->and($html)->toContain('Remaining: 3.5');
});
