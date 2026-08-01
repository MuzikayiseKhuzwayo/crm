<?php

use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use VentureDrake\LaravelCrm\Jobs\SendImportPasswordReset;
use VentureDrake\LaravelCrm\Livewire\Users\UserImport;

/**
 * Render-stub subclass -- see ChatAuthorizationTest for the rationale.
 */
class AuthzUserImport extends UserImport
{
    public function render()
    {
        return '<div></div>';
    }
}

/*
 * Both startImport() and processNextChunk() are guarded. processNextChunk() is the one
 * that actually writes users, and every public Livewire method is directly invokable
 * from the client -- so guarding only the entry point would leave the worker exploitable.
 */

it('forbids starting a user import without the create users permission', function () {
    $this->actingAsUserWithPermissions(['view crm users']);

    Livewire::test(AuthzUserImport::class)
        ->call('startImport')
        ->assertForbidden();
});

it('allows starting a user import with the create users permission', function () {
    $this->actingAsUserWithPermissions(['view crm users', 'create crm users']);

    Livewire::test(AuthzUserImport::class)
        ->call('startImport')
        ->assertOk();
});

it('forbids processing an import chunk without the create users permission and creates no user', function () {
    $this->actingAsUserWithPermissions(['view crm users']);

    session(['crm_user_import_preview' => [[
        'name' => 'Imported Person',
        'email' => 'imported@example.test',
        'crm_access' => 1,
        'errors' => [],
    ]]]);

    $before = User::count();

    Livewire::test(AuthzUserImport::class)
        ->set('processing', true)
        ->set('totalToProcess', 1)
        ->call('processNextChunk')
        ->assertForbidden();

    expect(User::count())->toBe($before)
        ->and(User::where('email', 'imported@example.test')->exists())->toBeFalse();
});

it('allows processing an import chunk with the create users permission', function () {
    // A successful import queues a password-reset job; fake the queue so the assertion
    // stays on the authorization contract rather than the mailer/token side effects.
    Queue::fake();

    $this->actingAsUserWithPermissions(['view crm users', 'create crm users']);

    session(['crm_user_import_preview' => [[
        'name' => 'Imported Person',
        'email' => 'imported@example.test',
        'crm_access' => 1,
        'errors' => [],
    ]]]);

    Livewire::test(AuthzUserImport::class)
        ->set('processing', true)
        ->set('totalToProcess', 1)
        ->call('processNextChunk')
        ->assertOk();

    expect(User::where('email', 'imported@example.test')->exists())->toBeTrue();

    Queue::assertPushed(SendImportPasswordReset::class);
});
