<?php

use Livewire\Livewire;
use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Livewire\Features\FeatureIndex;
use VentureDrake\LaravelCrm\Livewire\Monitors\MonitorIndex;
use VentureDrake\LaravelCrm\Models\Feature;
use VentureDrake\LaravelCrm\Models\FeatureStatus;
use VentureDrake\LaravelCrm\Models\Monitor;
use VentureDrake\LaravelCrm\Tests\Stubs\User;

it('calculates metrics and renders feature index correctly', function () {
    $user = User::create(['name' => 'Feature Admin', 'email' => 'feature@example.com']);
    $this->actingAs($user);

    $status = FeatureStatus::create(['name' => 'In Progress', 'color' => '#ff9900']);

    Feature::create([
        'external_id' => Uuid::uuid4()->toString(),
        'feature_id' => 'FEAT-1001',
        'title' => 'Automated Webhook Monitoring',
        'feature_status_id' => $status->id,
        'votes_count' => 12,
        'is_public' => true,
    ]);

    Livewire::test(FeatureIndex::class)
        ->assertViewHas('metrics', function ($metrics) {
            return $metrics['total'] === 1 && $metrics['votes'] === 12 && $metrics['in_progress'] === 1;
        });
});

it('calculates monitor health metrics and allows manual checkNow execution', function () {
    $user = User::create(['name' => 'Monitor Admin', 'email' => 'monitor@example.com']);
    $this->actingAs($user);

    $monitor = Monitor::create([
        'external_id' => Uuid::uuid4()->toString(),
        'monitor_id' => 'MON-1001',
        'name' => 'API Gateway Uptime',
        'url' => 'https://example.com/health',
        'last_status' => 'up',
        'last_response_time' => 120,
    ]);

    Livewire::test(MonitorIndex::class)
        ->assertViewHas('metrics', function ($metrics) {
            return $metrics['total'] === 1 && $metrics['up'] === 1 && $metrics['avg_response'] === 120;
        })
        ->call('checkNow', $monitor->id)
        ->assertHasNoErrors();
});
