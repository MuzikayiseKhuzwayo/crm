<?php

use Illuminate\Support\Facades\Route;
use VentureDrake\LaravelCrm\Http\Controllers\Api\V2\AuthController;
use VentureDrake\LaravelCrm\Http\Controllers\Api\V2\AutomationController;
use VentureDrake\LaravelCrm\Http\Controllers\Api\V2\DealController;
use VentureDrake\LaravelCrm\Http\Controllers\Api\V2\DeliveryController;
use VentureDrake\LaravelCrm\Http\Controllers\Api\V2\FeatureController;
use VentureDrake\LaravelCrm\Http\Controllers\Api\V2\InvoiceController;
use VentureDrake\LaravelCrm\Http\Controllers\Api\V2\LeadController;
use VentureDrake\LaravelCrm\Http\Controllers\Api\V2\MonitorController;
use VentureDrake\LaravelCrm\Http\Controllers\Api\V2\OrderController;
use VentureDrake\LaravelCrm\Http\Controllers\Api\V2\OrganizationController;
use VentureDrake\LaravelCrm\Http\Controllers\Api\V2\PersonController;
use VentureDrake\LaravelCrm\Http\Controllers\Api\V2\ProductController;
use VentureDrake\LaravelCrm\Http\Controllers\Api\V2\PurchaseOrderController;
use VentureDrake\LaravelCrm\Http\Controllers\Api\V2\QuoteController;
use VentureDrake\LaravelCrm\Http\Controllers\Api\V2\TaskController;

/*
 * Laravel CRM API routes (v2).
 *
 * This file is loaded by LaravelCrmServiceProvider::registerRoutes() under
 * the `crm/api/v2` prefix with the `api`, `laravel-crm.api.json`, and
 * `throttle:laravel-crm-api` middleware applied. Authenticated routes layer
 * `auth:sanctum`, `crm-api`, and `laravel-crm.api.team` on top.
 */

Route::post('auth/token', [AuthController::class, 'issueToken'])
    ->middleware('throttle:6,1')
    ->name('laravel-crm.api.v2.auth.token.issue');

Route::middleware(['auth:sanctum', 'crm-api', 'laravel-crm.api.team'])->group(function () {
    Route::get('auth/me', [AuthController::class, 'me'])
        ->name('laravel-crm.api.v2.auth.me');

    Route::delete('auth/token', [AuthController::class, 'revokeToken'])
        ->name('laravel-crm.api.v2.auth.token.revoke');

    Route::apiResource('leads', LeadController::class)
        ->names('laravel-crm.api.v2.leads')
        ->scoped(['lead' => 'external_id']);

    Route::apiResource('products', ProductController::class)
        ->names('laravel-crm.api.v2.products')
        ->scoped(['product' => 'external_id']);

    Route::apiResource('organizations', OrganizationController::class)
        ->names('laravel-crm.api.v2.organizations')
        ->scoped(['organization' => 'external_id']);

    Route::apiResource('people', PersonController::class)
        ->parameters(['people' => 'person'])
        ->names('laravel-crm.api.v2.people')
        ->scoped(['person' => 'external_id']);

    Route::apiResource('deals', DealController::class)
        ->names('laravel-crm.api.v2.deals')
        ->scoped(['deal' => 'external_id']);

    Route::apiResource('quotes', QuoteController::class)
        ->names('laravel-crm.api.v2.quotes')
        ->scoped(['quote' => 'external_id']);

    Route::apiResource('orders', OrderController::class)
        ->names('laravel-crm.api.v2.orders')
        ->scoped(['order' => 'external_id']);

    Route::apiResource('invoices', InvoiceController::class)
        ->names('laravel-crm.api.v2.invoices')
        ->scoped(['invoice' => 'external_id']);

    Route::apiResource('tasks', TaskController::class)
        ->names('laravel-crm.api.v2.tasks')
        ->scoped(['task' => 'external_id']);

    Route::apiResource('deliveries', DeliveryController::class)
        ->names('laravel-crm.api.v2.deliveries')
        ->scoped(['delivery' => 'external_id']);

    Route::apiResource('purchase-orders', PurchaseOrderController::class)
        ->names('laravel-crm.api.v2.purchase-orders')
        ->scoped(['purchase_order' => 'external_id']);

    Route::apiResource('features', FeatureController::class)
        ->names('laravel-crm.api.v2.features')
        ->scoped(['feature' => 'external_id']);

    Route::apiResource('monitors', MonitorController::class)
        ->names('laravel-crm.api.v2.monitors')
        ->scoped(['monitor' => 'external_id']);

    // Automation & Operational Bridge APIs
    Route::post('automations/sync-lead-stages', [AutomationController::class, 'syncLeadStages'])
        ->name('laravel-crm.api.v2.automations.sync-lead-stages');
    Route::post('automations/generate-playbook-tasks', [AutomationController::class, 'generatePlaybookTasks'])
        ->name('laravel-crm.api.v2.automations.generate-playbook-tasks');
    Route::get('system/health', [AutomationController::class, 'health'])
        ->name('laravel-crm.api.v2.system.health');
});
