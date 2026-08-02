<?php

use VentureDrake\LaravelCrm\Models\Activity;
use VentureDrake\LaravelCrm\Models\Deal;
use VentureDrake\LaravelCrm\Models\DealProduct;
use VentureDrake\LaravelCrm\Models\Order;
use VentureDrake\LaravelCrm\Models\OrderProduct;
use VentureDrake\LaravelCrm\Models\Quote;
use VentureDrake\LaravelCrm\Models\QuoteProduct;

test('activities index requires view permission', function () {
    $this->actingAsUser(['crm_access' => 1, 'crm_permissions' => json_encode([])]);

    $this->get(route('laravel-crm.activities.index'))->assertForbidden();
});

test('activities index is accessible with view permission', function () {
    $this->actingAsUser(['crm_access' => 1, 'crm_permissions' => json_encode(['view crm activities'])]);

    $this->get(route('laravel-crm.activities.index'))->assertOk();
});

test('activity show requires view permission', function () {
    $activity = Activity::create(['description' => 'Test activity']);

    $this->actingAsUser(['crm_access' => 1, 'crm_permissions' => json_encode([])]);

    $this->get(route('laravel-crm.activities.show', $activity))->assertForbidden();
});

test('activity show is accessible with view permission', function () {
    $activity = Activity::create(['description' => 'Test activity']);

    $this->actingAsUser(['crm_access' => 1, 'crm_permissions' => json_encode(['view crm activities'])]);

    $this->get(route('laravel-crm.activities.show', $activity))->assertOk();
});

test('activity destroy requires delete permission', function () {
    $activity = Activity::create(['description' => 'Test activity']);

    $this->actingAsUser(['crm_access' => 1, 'crm_permissions' => json_encode(['view crm activities'])]);

    $this->delete(route('laravel-crm.activities.destroy', $activity))->assertForbidden();
});

test('activity destroy is accessible with delete permission', function () {
    $activity = Activity::create(['description' => 'Test activity']);

    $this->actingAsUser(['crm_access' => 1, 'crm_permissions' => json_encode(['delete crm activities'])]);

    $this->delete(route('laravel-crm.activities.destroy', $activity))->assertSuccessful();
});

/*
 * DealProductController/QuoteProductController/OrderProductController render legacy v1 Blade
 * views (resources/v1/views/*) that aren't registered in this package's test harness. For their
 * create/edit actions we only assert the authorization gate itself (not forbidden), since full
 * view rendering is outside what this test environment supports.
 */

test('deal product create requires edit permission on the parent deal', function () {
    $deal = Deal::create(['title' => 'Big Deal']);

    $this->actingAsUser(['crm_access' => 1, 'crm_permissions' => json_encode(['view crm deals'])]);

    $this->get(route('laravel-crm.deal-products.create', $deal))->assertForbidden();
});

test('deal product create is accessible with edit permission on the parent deal', function () {
    $deal = Deal::create(['title' => 'Big Deal']);

    $this->actingAsUser(['crm_access' => 1, 'crm_permissions' => json_encode(['edit crm deals'])]);

    $response = $this->get(route('laravel-crm.deal-products.create', $deal));

    expect($response->status())->not->toBe(403);
});

test('deal product edit requires edit permission on the parent deal', function () {
    $deal = Deal::create(['title' => 'Big Deal']);
    $product = DealProduct::create(['external_id' => 'dp-1', 'deal_id' => $deal->id]);

    $this->actingAsUser(['crm_access' => 1, 'crm_permissions' => json_encode(['view crm deals'])]);

    $this->get(route('laravel-crm.deal-products.edit', [$deal, $product]))->assertForbidden();
});

test('deal product edit is accessible with edit permission on the parent deal', function () {
    $deal = Deal::create(['title' => 'Big Deal']);
    $product = DealProduct::create(['external_id' => 'dp-1', 'deal_id' => $deal->id]);

    $this->actingAsUser(['crm_access' => 1, 'crm_permissions' => json_encode(['edit crm deals'])]);

    $response = $this->get(route('laravel-crm.deal-products.edit', [$deal, $product]));

    expect($response->status())->not->toBe(403);
});

test('quote product create requires edit permission on the parent quote', function () {
    $quote = Quote::create(['title' => 'Big Quote']);

    $this->actingAsUser(['crm_access' => 1, 'crm_permissions' => json_encode(['view crm quotes'])]);

    $this->get(route('laravel-crm.quote-products.create', $quote))->assertForbidden();
});

test('quote product create is accessible with edit permission on the parent quote', function () {
    $quote = Quote::create(['title' => 'Big Quote']);

    $this->actingAsUser(['crm_access' => 1, 'crm_permissions' => json_encode(['edit crm quotes'])]);

    $response = $this->get(route('laravel-crm.quote-products.create', $quote));

    expect($response->status())->not->toBe(403);
});

test('quote product edit requires edit permission on the parent quote', function () {
    $quote = Quote::create(['title' => 'Big Quote']);
    $product = QuoteProduct::create(['external_id' => 'qp-1', 'quote_id' => $quote->id]);

    $this->actingAsUser(['crm_access' => 1, 'crm_permissions' => json_encode(['view crm quotes'])]);

    $this->get(route('laravel-crm.quote-products.edit', [$quote, $product]))->assertForbidden();
});

test('quote product edit is accessible with edit permission on the parent quote', function () {
    $quote = Quote::create(['title' => 'Big Quote']);
    $product = QuoteProduct::create(['external_id' => 'qp-1', 'quote_id' => $quote->id]);

    $this->actingAsUser(['crm_access' => 1, 'crm_permissions' => json_encode(['edit crm quotes'])]);

    $response = $this->get(route('laravel-crm.quote-products.edit', [$quote, $product]));

    expect($response->status())->not->toBe(403);
});

test('order product create requires edit permission on the parent order', function () {
    $order = Order::create(['description' => 'Big Order']);

    $this->actingAsUser(['crm_access' => 1, 'crm_permissions' => json_encode(['view crm orders'])]);

    $this->get(route('laravel-crm.order-products.create', $order))->assertForbidden();
});

test('order product create is accessible with edit permission on the parent order', function () {
    $order = Order::create(['description' => 'Big Order']);

    $this->actingAsUser(['crm_access' => 1, 'crm_permissions' => json_encode(['edit crm orders'])]);

    $response = $this->get(route('laravel-crm.order-products.create', $order));

    expect($response->status())->not->toBe(403);
});

test('order product edit requires edit permission on the parent order', function () {
    $order = Order::create(['description' => 'Big Order']);
    $product = OrderProduct::create(['external_id' => 'op-1', 'order_id' => $order->id]);

    $this->actingAsUser(['crm_access' => 1, 'crm_permissions' => json_encode(['view crm orders'])]);

    $this->get(route('laravel-crm.order-products.edit', [$order, $product]))->assertForbidden();
});

test('order product edit is accessible with edit permission on the parent order', function () {
    $order = Order::create(['description' => 'Big Order']);
    $product = OrderProduct::create(['external_id' => 'op-1', 'order_id' => $order->id]);

    $this->actingAsUser(['crm_access' => 1, 'crm_permissions' => json_encode(['edit crm orders'])]);

    $response = $this->get(route('laravel-crm.order-products.edit', [$order, $product]));

    expect($response->status())->not->toBe(403);
});
