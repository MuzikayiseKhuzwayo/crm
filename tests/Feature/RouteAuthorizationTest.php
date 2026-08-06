<?php

use Illuminate\Support\Facades\Gate;
use VentureDrake\LaravelCrm\Models\Activity;
use VentureDrake\LaravelCrm\Models\Deal;
use VentureDrake\LaravelCrm\Models\Order;
use VentureDrake\LaravelCrm\Models\ProductAttribute;
use VentureDrake\LaravelCrm\Models\Quote;
use VentureDrake\LaravelCrm\Models\Setting;

/**
 * Route-level authorization for the groups closed in US-006.
 *
 * Every deny case asserts a 403 from the can: middleware. Every allow case asserts the
 * response is NOT a 403 — the middleware let the request through to the controller. We
 * deliberately do not assert 200 on the allow path: these controllers render views with
 * their own fixture needs, and "the gate passed" is the contract under test.
 */
function assertRouteForbidden(string $name, array $params = []): void
{
    test()->get(route($name, $params))->assertForbidden();
}

/**
 * Allow-path assertion.
 *
 * A bare "not 403" is weak on its own: some of these controllers abort(404) by design
 * (DealProductController@index) and others 500 on unseeded view fixtures, so a vacuous
 * pass is easy. Callers therefore pair this with an explicit Gate::allows() assertion
 * against the exact ability + argument the can: middleware constructs.
 */
function assertRouteNotForbidden(string $name, array $params = []): void
{
    $status = test()->get(route($name, $params))->status();

    expect($status)->not->toBe(403);
}

/* -------------------------------------------------------------------------
 | Activities
 | ------------------------------------------------------------------------- */

it('forbids the activities group without the matching crm activities permissions', function () {
    $this->actingAsUserWithPermissions([]);
    $activity = Activity::create(['log_name' => 'default', 'description' => 'seeded']);

    assertRouteForbidden('laravel-crm.activities.index');
    assertRouteForbidden('laravel-crm.activities.create');
    assertRouteForbidden('laravel-crm.activities.show', [$activity->id]);
    assertRouteForbidden('laravel-crm.activities.edit', [$activity->id]);
});

it('allows the activities group for a user holding the crm activities permissions', function () {
    $this->actingAsUserWithPermissions([
        'view crm activities',
        'create crm activities',
        'edit crm activities',
        'delete crm activities',
    ]);
    $activity = Activity::create(['log_name' => 'default', 'description' => 'seeded']);

    assertRouteNotForbidden('laravel-crm.activities.index');
    assertRouteNotForbidden('laravel-crm.activities.create');
    assertRouteNotForbidden('laravel-crm.activities.show', [$activity->id]);
    assertRouteNotForbidden('laravel-crm.activities.edit', [$activity->id]);
});

it('resolves a bound Activity for the parameter-form can: middleware', function () {
    // {activity} only reaches the gate as a model because ActivityController@show
    // type-hints Activity $activity. If that stopped being true the guard would receive
    // a raw id, Gate::getPolicyFor() would return null, and this allow case would 403.
    $this->actingAsUserWithPermissions(['view crm activities']);
    $activity = Activity::create(['log_name' => 'default', 'description' => 'seeded']);

    assertRouteNotForbidden('laravel-crm.activities.show', [$activity->id]);

    $this->actingAsUserWithPermissions([]);
    assertRouteForbidden('laravel-crm.activities.show', [$activity->id]);
});

/* -------------------------------------------------------------------------
 | Deal / Quote / Order product sub-resources
 | ------------------------------------------------------------------------- */

dataset('productSubResources', [
    'deal' => ['laravel-crm.deal-products', 'deal', 'edit crm deals', Deal::class],
    'quote' => ['laravel-crm.quote-products', 'quote', 'edit crm quotes', Quote::class],
    'order' => ['laravel-crm.order-products', 'order', 'edit crm orders', Order::class],
]);

it('forbids the product sub-resource group without the parent edit permission', function (
    string $prefix,
    string $param,
    string $permission,
    string $model
) {
    $this->actingAsUserWithPermissions([]);
    $parent = $model::create(['title' => 'Seeded']);

    assertRouteForbidden($prefix.'.index', [$param => $parent->getRouteKey()]);
    assertRouteForbidden($prefix.'.create', [$param => $parent->getRouteKey()]);
    assertRouteForbidden($prefix.'.show', [$param => $parent->getRouteKey(), 'product' => 1]);
})->with('productSubResources');

it('allows the product sub-resource group for a user holding the parent edit permission', function (
    string $prefix,
    string $param,
    string $permission,
    string $model
) {
    $this->actingAsUserWithPermissions([$permission]);
    $parent = $model::create(['title' => 'Seeded']);

    // Positive proof at the exact layer the middleware uses: can:manageProducts,<Model>
    // resolves to Gate::allows('manageProducts', <Model>::class).
    expect(Gate::allows('manageProducts', $model))->toBeTrue();

    assertRouteNotForbidden($prefix.'.index', [$param => $parent->getRouteKey()]);
    assertRouteNotForbidden($prefix.'.create', [$param => $parent->getRouteKey()]);
})->with('productSubResources');

it('gates the product sub-resources on manageProducts, not on crm products', function (
    string $prefix,
    string $param,
    string $permission,
    string $model
) {
    // Manager and Employee hold no crm products permission at all. Gating line items on
    // ProductPolicy would stop them building a quote, so manageProducts keys off the
    // parent entity's edit permission instead. This is the anti-regression guard.
    $this->actingAsUserWithPermissions(['view crm products', 'edit crm products']);
    $parent = $model::create(['title' => 'Seeded']);

    expect(Gate::allows('manageProducts', $model))->toBeFalse();

    assertRouteForbidden($prefix.'.index', [$param => $parent->getRouteKey()]);
})->with('productSubResources');

it('forbids the stray deals create-product route without the deal edit permission', function () {
    $this->actingAsUserWithPermissions([]);

    assertRouteForbidden('laravel-crm.deal-products.create-product');
});

it('allows the stray deals create-product route with the deal edit permission', function () {
    $this->actingAsUserWithPermissions(['edit crm deals']);

    assertRouteNotForbidden('laravel-crm.deal-products.create-product');
});

/* -------------------------------------------------------------------------
 | Product attributes — the pre-existing 403-for-everyone fix
 | ------------------------------------------------------------------------- */

it('forbids the product-attributes routes without the matching permissions', function () {
    $this->actingAsUserWithPermissions([]);
    $attribute = ProductAttribute::create(['name' => 'Colour']);

    assertRouteForbidden('laravel-crm.product-attributes.index');
    assertRouteForbidden('laravel-crm.product-attributes.create');
    assertRouteForbidden('laravel-crm.product-attributes.show', [$attribute->id]);
    assertRouteForbidden('laravel-crm.product-attributes.edit', [$attribute->id]);
});

it('allows the product-attributes routes for a user holding the permissions', function () {
    // Before US-006, show/edit/update/destroy were 403 for every user including Owner:
    // the URI named {productCategory} while the guard read productAttribute, so
    // Authorize::getModel() returned null and no policy was ever consulted.
    // (index/create/store were fine — their lowercase class-string still resolved a
    // policy via Laravel's case-insensitive guesser fallback.)
    $this->actingAsUserWithPermissions([
        'view crm product attributes',
        'create crm product attributes',
        'edit crm product attributes',
        'delete crm product attributes',
    ]);
    $attribute = ProductAttribute::create(['name' => 'Colour']);

    assertRouteNotForbidden('laravel-crm.product-attributes.index');
    assertRouteNotForbidden('laravel-crm.product-attributes.create');
    assertRouteNotForbidden('laravel-crm.product-attributes.show', [$attribute->id]);
    assertRouteNotForbidden('laravel-crm.product-attributes.edit', [$attribute->id]);
});

it('binds the product-attributes URI parameter so the policy is consulted', function () {
    // The URI parameter must be {productAttribute} so it matches BOTH the can: argument
    // and ProductAttributeController's ProductAttribute $productAttribute type-hint.
    $route = app('router')->getRoutes()->getByName('laravel-crm.product-attributes.show');

    expect($route->uri())->toContain('{productAttribute}')
        ->and($route->uri())->not->toContain('{productCategory}')
        ->and($route->middleware())->toContain('can:view,productAttribute');
});

/* -------------------------------------------------------------------------
 | Updates — the page the sidebar already gated but the route did not
 | ------------------------------------------------------------------------- */

/**
 * Seed the rows UpdateController@index reads so hitting the route stays offline.
 *
 * Two things matter here. Without an install_id row the controller POSTs to
 * api.laravelcrm.com inside a try {} that swallows the failure — slow and flaky with
 * no visible cause. And config('laravel-crm.version') is null in tests (only a host's
 * published config defines it), so the controller's updateOrCreate would otherwise
 * write a null version over anything we seed.
 */
function seedUpdatesRouteSettings(string $current = '2.3.0', string $latest = '2.3.0'): void
{
    config(['laravel-crm.version' => $current]);

    Setting::create(['name' => 'install_id', 'value' => 'route-auth-updates']);
    Setting::create(['name' => 'version', 'value' => $current]);
    Setting::create(['name' => 'version_latest', 'value' => $latest]);

    app('laravel-crm.settings')->forgetCache();
}

it('forbids the updates page without the view crm updates permission', function () {
    // A pending update is seeded so the page would have something to disclose if the
    // gate let this user through — assertDontSee then means "no version state leaked",
    // not just "the response happened to be short".
    seedUpdatesRouteSettings('2.2.0', '2.10.0');
    $this->actingAsUserWithPermissions([]);

    $this->get(route('laravel-crm.updates.index'))
        ->assertForbidden()
        ->assertDontSee('Updated version of Laravel CRM is available');
});

it('allows the updates page for a user holding view crm updates', function () {
    seedUpdatesRouteSettings();
    $this->actingAsUserWithPermissions(['view crm updates']);

    $this->get(route('laravel-crm.updates.index'))->assertOk();
});

it('gates the updates route on the view crm updates permission', function () {
    // A bare permission string, not a can:ability,model pair — there is no Update model.
    // Spatie's Gate::before routes it through checkPermissionTo(), which returns false
    // rather than throwing when the permission has never been seeded.
    $route = app('router')->getRoutes()->getByName('laravel-crm.updates.index');

    expect($route->middleware())->toContain('can:view crm updates');
});
