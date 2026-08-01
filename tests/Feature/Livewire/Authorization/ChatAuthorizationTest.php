<?php

use Livewire\Livewire;
use Ramsey\Uuid\Uuid;
use VentureDrake\LaravelCrm\Livewire\Chat\ChatIndex;
use VentureDrake\LaravelCrm\Livewire\Chat\ChatShow;
use VentureDrake\LaravelCrm\Models\ChatConversation;
use VentureDrake\LaravelCrm\Models\ChatVisitor;
use VentureDrake\LaravelCrm\Models\ChatWidget;
use VentureDrake\LaravelCrm\Models\Lead;

/**
 * Render-stub subclasses. Overriding only render() leaves the real action methods --
 * and the $this->authorize() guards inside them -- intact, so these tests exercise the
 * production authorization path against the real ChatConversationPolicy.
 */
class AuthzChatShow extends ChatShow
{
    public function render()
    {
        return '<div></div>';
    }
}
class AuthzChatIndex extends ChatIndex
{
    public function render()
    {
        return '<div></div>';
    }
}

function authzChatConversation(): ChatConversation
{
    $widget = ChatWidget::create([
        'external_id' => Uuid::uuid4()->toString(),
        'public_key' => Uuid::uuid4()->toString(),
        'name' => 'Authz Widget',
    ]);

    $visitor = ChatVisitor::create([
        'external_id' => Uuid::uuid4()->toString(),
        'chat_widget_id' => $widget->id,
        'visitor_token' => Uuid::uuid4()->toString(),
        'name' => 'Jane Visitor',
        'email' => 'jane@example.test',
    ]);

    return ChatConversation::create([
        'external_id' => Uuid::uuid4()->toString(),
        'chat_widget_id' => $widget->id,
        'chat_visitor_id' => $visitor->id,
        'status' => 'open',
    ]);
}

it('forbids sending a chat reply without the reply permission and stores no message', function () {
    $this->actingAsUserWithPermissions(['view crm chat']);
    $conversation = authzChatConversation();

    Livewire::test(AuthzChatShow::class, ['conversation' => $conversation])
        ->set('body', 'Tampered reply')
        ->call('send')
        ->assertForbidden();

    expect($conversation->messages()->count())->toBe(0);
});

it('allows sending a chat reply with the reply permission', function () {
    $this->actingAsUserWithPermissions(['view crm chat', 'reply crm chat']);
    $conversation = authzChatConversation();

    Livewire::test(AuthzChatShow::class, ['conversation' => $conversation])
        ->set('body', 'Hello there')
        ->call('send')
        ->assertOk();

    expect($conversation->messages()->count())->toBe(1);
});

it('forbids closing a conversation from the show page without the reply permission', function () {
    $this->actingAsUserWithPermissions(['view crm chat']);
    $conversation = authzChatConversation();

    Livewire::test(AuthzChatShow::class, ['conversation' => $conversation])
        ->call('close')
        ->assertForbidden();

    expect($conversation->fresh()->status)->toBe('open');
});

it('allows closing a conversation from the show page with the reply permission', function () {
    $this->actingAsUserWithPermissions(['view crm chat', 'reply crm chat']);
    $conversation = authzChatConversation();

    Livewire::test(AuthzChatShow::class, ['conversation' => $conversation])
        ->call('close')
        ->assertOk();

    expect($conversation->fresh()->status)->toBe('closed');
});

it('forbids converting a conversation to a lead without the create lead permission', function () {
    // Holds full chat access but no lead permissions -- convertToLead mints a Lead, so
    // the guard keys off LeadPolicy, not ChatConversationPolicy.
    $this->actingAsUserWithPermissions(['view crm chat', 'reply crm chat']);
    $conversation = authzChatConversation();
    $before = Lead::count();

    Livewire::test(AuthzChatShow::class, ['conversation' => $conversation])
        ->call('convertToLead')
        ->assertForbidden();

    expect(Lead::count())->toBe($before)
        ->and($conversation->fresh()->lead_id)->toBeNull();
});

it('allows converting a conversation to a lead with the create lead permission', function () {
    $this->actingAsUserWithPermissions(['view crm chat', 'reply crm chat', 'create crm leads', 'create crm people']);
    $conversation = authzChatConversation();

    Livewire::test(AuthzChatShow::class, ['conversation' => $conversation])
        ->call('convertToLead')
        ->assertOk();

    expect($conversation->fresh()->lead_id)->not->toBeNull();
});

it('forbids deleting a conversation from the index without the delete permission', function () {
    $this->actingAsUserWithPermissions(['view crm chat']);
    $conversation = authzChatConversation();

    Livewire::test(AuthzChatIndex::class)
        ->call('delete', $conversation->id)
        ->assertForbidden();

    expect(ChatConversation::find($conversation->id))->not->toBeNull();
});

it('allows deleting a conversation from the index with the delete permission', function () {
    $this->actingAsUserWithPermissions(['view crm chat', 'delete crm chat']);
    $conversation = authzChatConversation();

    Livewire::test(AuthzChatIndex::class)
        ->call('delete', $conversation->id)
        ->assertOk();

    expect(ChatConversation::find($conversation->id))->toBeNull();
});

it('forbids closing a conversation from the index without the reply permission', function () {
    $this->actingAsUserWithPermissions(['view crm chat']);
    $conversation = authzChatConversation();

    Livewire::test(AuthzChatIndex::class)
        ->call('close', $conversation->id)
        ->assertForbidden();

    expect($conversation->fresh()->status)->toBe('open');
});

it('forbids converting to a lead from the index without the create lead permission', function () {
    $this->actingAsUserWithPermissions(['view crm chat', 'reply crm chat']);
    $conversation = authzChatConversation();
    $before = Lead::count();

    Livewire::test(AuthzChatIndex::class)
        ->call('convertToLead', $conversation->id)
        ->assertForbidden();

    expect(Lead::count())->toBe($before);
});
