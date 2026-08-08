<?php

/*
 * The line-item totals on the quote/order/invoice forms.
 *
 * The money inputs are masked in the browser ($money), so the line values come
 * back as formatted strings — and Livewire can send the products array wholesale
 * rather than one "{index}.{attribute}" at a time. Both used to leave the footer
 * showing 0: the first because "14,000" casts to 14.0, the second because the
 * hook zeroed the totals and then returned early without summing anything.
 */

use Livewire\Livewire;
use VentureDrake\LaravelCrm\Livewire\ModelProducts;
use VentureDrake\LaravelCrm\Models\Product;
use VentureDrake\LaravelCrm\Models\Quote;

function quoteWithLines(): Quote
{
    $productA = Product::create(['name' => 'A']);
    $productB = Product::create(['name' => 'B']);

    // Stored totals are deliberately stale — the lines are the source of truth.
    $quote = Quote::create(['title' => 'Q', 'currency' => 'USD', 'subtotal' => 180.13, 'tax' => 18.01, 'total' => 198.14]);

    $quote->quoteProducts()->create([
        'product_id' => $productA->id, 'quantity' => 3, 'price' => 4500,
        // tax_amount has no mutator on the line models, so it is stored in cents.
        'tax_rate' => 10, 'tax_amount' => 135000, 'amount' => 13500,
    ]);

    $quote->quoteProducts()->create([
        'product_id' => $productB->id, 'quantity' => 4, 'price' => 3500,
        'tax_rate' => 10, 'tax_amount' => 140000, 'amount' => 14000,
    ]);

    return $quote;
}

test('it totals the lines it loaded', function () {
    $this->actingAsUser();

    Livewire::test(ModelProducts::class, ['model' => quoteWithLines()])
        ->assertSet('sub_total', 27500.0)
        ->assertSet('tax', 2750.0)
        ->assertSet('total', 30250.0);
});

test('it keeps the totals when the products array is replaced wholesale', function () {
    $this->actingAsUser();

    $component = Livewire::test(ModelProducts::class, ['model' => quoteWithLines()]);

    $component->set('products', $component->get('products'));

    $component->assertSet('sub_total', 27500.0)
        ->assertSet('tax', 2750.0)
        ->assertSet('total', 30250.0);
});

test('it totals masked money values', function () {
    $this->actingAsUser();

    $component = Livewire::test(ModelProducts::class, ['model' => quoteWithLines()]);

    $products = $component->get('products');
    $products[0]['amount'] = '13,500.00';
    $products[0]['tax_amount'] = '1,350.00';
    $products[1]['amount'] = '$14,000.00';
    $products[1]['tax_amount'] = '$1,400.00';

    $component->set('products', $products);

    $component->assertSet('sub_total', 27500.0)
        ->assertSet('tax', 2750.0)
        ->assertSet('total', 30250.0);
});

test('it recalculates a line from a masked unit price', function () {
    $this->actingAsUser();

    $component = Livewire::test(ModelProducts::class, ['model' => quoteWithLines()]);

    $component->set('products.1.unit_price', '1,200.00');

    expect($component->get('products.1.amount'))->toBe(4800.0);
    $component->assertSet('sub_total', 18300.0);
});

test('it retotals when a line is removed', function () {
    $this->actingAsUser();

    $component = Livewire::test(ModelProducts::class, ['model' => quoteWithLines()]);

    $component->call('remove', 0);

    $component->assertSet('sub_total', 14000.0)
        ->assertSet('tax', 1400.0)
        ->assertSet('total', 15400.0);
});
