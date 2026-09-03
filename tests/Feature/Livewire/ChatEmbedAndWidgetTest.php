<?php

use Ramsey\Uuid\Uuid;
use Livewire\Livewire;
use VentureDrake\LaravelCrm\Livewire\Chat\ChatIndex;
use VentureDrake\LaravelCrm\Models\ChatWidget;
use VentureDrake\LaravelCrm\Tests\Stubs\User;

it('renders chat index page and resolves active widget embed snippet', function () {
    $user = User::create(['name' => 'Agent Admin', 'email' => 'admin@example.com']);
    $this->actingAs($user);

    $widget = ChatWidget::create([
        'external_id' => Uuid::uuid4()->toString(),
        'public_key' => Uuid::uuid4()->toString(),
        'name' => 'Main Website Chat Widget',
        'is_active' => true,
    ]);

    $test = Livewire::test(ChatIndex::class);

    expect($test->instance()->activeWidget->id)->toBe($widget->id);
    expect($widget->embedSnippet())->toContain($widget->public_key);
});

it('toggles embed setup modal and widget selector on chat index', function () {
    $user = User::create(['name' => 'Agent Admin 2', 'email' => 'admin2@example.com']);
    $this->actingAs($user);

    $widget1 = ChatWidget::create([
        'external_id' => Uuid::uuid4()->toString(),
        'public_key' => Uuid::uuid4()->toString(),
        'name' => 'Widget 1',
        'is_active' => true,
    ]);

    $widget2 = ChatWidget::create([
        'external_id' => Uuid::uuid4()->toString(),
        'public_key' => Uuid::uuid4()->toString(),
        'name' => 'Widget 2',
        'is_active' => false,
    ]);

    Livewire::test(ChatIndex::class)
        ->set('showEmbedModal', true)
        ->assertSet('showEmbedModal', true)
        ->set('selectedWidgetId', $widget2->id)
        ->assertSet('selectedWidgetId', $widget2->id);
});
