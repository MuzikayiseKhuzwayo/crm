<?php

use Livewire\Livewire;
use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Livewire\Dashboard;
use VentureDrake\LaravelCrm\Models\Invoice;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Task;
use VentureDrake\LaravelCrm\Tests\Stubs\User;

it('filters dashboard visual metrics by period and renders clickable links for attention instances', function () {
    $user = User::create(['name' => 'Dashboard Executive', 'email' => 'exec@example.com']);
    $this->actingAs($user);

    $lead = Lead::create([
        'external_id' => Uuid::uuid4()->toString(),
        'title' => 'Dashboard High Priority Lead',
        'amount' => 1500000,
        'currency' => 'USD',
        'user_owner_id' => $user->id,
    ]);

    $task = Task::create([
        'external_id' => Uuid::uuid4()->toString(),
        'name' => 'Review Executive Dashboard Metrics',
        'taskable_type' => get_class($lead),
        'taskable_id' => $lead->id,
        'due_at' => now()->addDay(),
        'user_owner_id' => $user->id,
    ]);

    $invoice = Invoice::create([
        'external_id' => Uuid::uuid4()->toString(),
        'invoice_id' => 'INV-9901',
        'amount_due' => 450000,
        'total' => 450000,
        'currency' => 'USD',
        'user_owner_id' => $user->id,
    ]);

    $leadUrl = route('laravel-crm.leads.show', $lead);
    $taskUrl = route('laravel-crm.tasks.show', $task);
    $invoiceUrl = route('laravel-crm.invoices.show', $invoice);

    Livewire::test(Dashboard::class)
        ->set('period', 'this_month')
        ->assertSeeHtml('href="' . $taskUrl . '"')
        ->assertSeeHtml('href="' . $leadUrl . '"')
        ->assertSeeHtml('href="' . $invoiceUrl . '"')
        ->set('period', 'last_30_days')
        ->assertSee('Last 30 Days')
        ->set('period', 'all_time')
        ->assertSee('All Time');
});
