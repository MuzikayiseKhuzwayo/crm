<?php

use VentureDrake\LaravelCrm\Models\Feature;
use VentureDrake\LaravelCrm\Models\Monitor;
use VentureDrake\LaravelCrm\Models\Task;
use VentureDrake\LaravelCrm\Tests\Stubs\User;

function apiTestUser(): User
{
    return User::create([
        'name' => 'API Admin',
        'email' => 'api-admin-'.uniqid().'@example.com',
        'password' => bcrypt('password'),
        'crm_access' => true,
    ]);
}

test('GET /crm/api/v2/system/health returns healthy telemetry status', function () {
    $user = apiTestUser();

    $this->actingAs($user, 'sanctum')
        ->getJson('/crm/api/v2/system/health')
        ->assertOk()
        ->assertJsonStructure(['status', 'timestamp', 'alerts_count', 'alerts']);
});

test('CRUD operations on /crm/api/v2/tasks', function () {
    $user = apiTestUser();

    $createResponse = $this->actingAs($user, 'sanctum')
        ->postJson('/crm/api/v2/tasks', [
            'name' => 'Follow up on Q4 proposal',
            'description' => 'Review custom terms with legal counsel',
            'due_at' => now()->addDays(2)->toIso8601String(),
        ]);

    $createResponse->assertStatus(201);
    $createResponse->assertJsonPath('data.name', 'Follow up on Q4 proposal');

    $taskId = $createResponse->json('data.id');

    $this->actingAs($user, 'sanctum')
        ->getJson("/crm/api/v2/tasks/{$taskId}")
        ->assertOk()
        ->assertJsonPath('data.name', 'Follow up on Q4 proposal');

    $this->actingAs($user, 'sanctum')
        ->putJson("/crm/api/v2/tasks/{$taskId}", [
            'name' => 'Follow up on Q4 proposal (Urgent)',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Follow up on Q4 proposal (Urgent)');

    $this->actingAs($user, 'sanctum')
        ->deleteJson("/crm/api/v2/tasks/{$taskId}")
        ->assertNoContent();

    expect(Task::where('external_id', $taskId)->count())->toBe(0);
});

test('CRUD operations on /crm/api/v2/features', function () {
    $user = apiTestUser();

    $createResponse = $this->actingAs($user, 'sanctum')
        ->postJson('/crm/api/v2/features', [
            'title' => 'Webhooks for deal stage transitions',
            'description' => 'Emit outgoing webhooks when deal moves to Won',
            'is_public' => true,
        ]);

    $createResponse->assertStatus(201);
    $createResponse->assertJsonPath('data.title', 'Webhooks for deal stage transitions');

    $featureId = $createResponse->json('data.id');

    $this->actingAs($user, 'sanctum')
        ->getJson("/crm/api/v2/features/{$featureId}")
        ->assertOk()
        ->assertJsonPath('data.title', 'Webhooks for deal stage transitions');

    $this->actingAs($user, 'sanctum')
        ->deleteJson("/crm/api/v2/features/{$featureId}")
        ->assertNoContent();
});

test('CRUD operations on /crm/api/v2/monitors', function () {
    $user = apiTestUser();

    $createResponse = $this->actingAs($user, 'sanctum')
        ->postJson('/crm/api/v2/monitors', [
            'name' => 'Production Webhook Gateway',
            'url' => 'https://api.example.com/health',
            'is_active' => true,
        ]);

    $createResponse->assertStatus(201);
    $createResponse->assertJsonPath('data.name', 'Production Webhook Gateway');

    $monitorId = $createResponse->json('data.id');

    $this->actingAs($user, 'sanctum')
        ->getJson("/crm/api/v2/monitors/{$monitorId}")
        ->assertOk()
        ->assertJsonPath('data.url', 'https://api.example.com/health');

    $this->actingAs($user, 'sanctum')
        ->deleteJson("/crm/api/v2/monitors/{$monitorId}")
        ->assertNoContent();
});

test('POST /crm/api/v2/automations/sync-lead-stages executes cleanly', function () {
    $user = apiTestUser();

    $this->actingAs($user, 'sanctum')
        ->postJson('/crm/api/v2/automations/sync-lead-stages', [
            'dry_run' => true,
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('dry_run', true);
});
