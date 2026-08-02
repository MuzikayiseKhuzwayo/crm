<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
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

/*
 * ---------------------------------------------------------------------------
 * Role assignment.
 *
 * The importer is the fifth role-assignment path, and the only one whose role
 * name arrives as free text. Both sources -- the CSV `role` column and the
 * wire:model-bound $defaultRole -- are attacker-controlled, so both must go
 * through Role::assignableBy(), the same predicate AssignableRole applies on
 * the create/edit/invite forms.
 * ---------------------------------------------------------------------------
 */

if (! function_exists('im007RoleTables')) {
    function im007RoleTables(): void
    {
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('team_id')->nullable();
                $table->string('name');
                $table->string('guard_name')->default('web');
                $table->string('description')->nullable();
                $table->boolean('crm_role')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('model_has_roles')) {
            Schema::create('model_has_roles', function (Blueprint $table) {
                $table->unsignedBigInteger('role_id');
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
            });
        }

        DB::table('roles')->delete();
        DB::table('model_has_roles')->delete();
    }
}

if (! function_exists('im007MakeRole')) {
    function im007MakeRole(string $name, ?int $teamId = null, int $crmRole = 1): int
    {
        return DB::table('roles')->insertGetId([
            'name' => $name,
            'guard_name' => 'web',
            'crm_role' => $crmRole,
            'team_id' => $teamId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

/** One preview row, ready to confirm. */
function im007PreviewRow(string $email, string $role = ''): array
{
    return [
        'name' => 'Imported Person',
        'email' => $email,
        'crm_access' => 1,
        'role' => $role,
        'errors' => [],
    ];
}

it('drops an Owner role named in the CSV when the importer is not an Owner', function () {
    Queue::fake();
    im007RoleTables();

    $this->actingAsUserWithPermissions(
        ['view crm users', 'create crm users'],
        ['crm_roles' => json_encode(['Admin'])]
    );

    $ownerRoleId = im007MakeRole('Owner');

    session(['crm_user_import_preview' => [im007PreviewRow('escalate@example.test', 'Owner')]]);

    Livewire::test(AuthzUserImport::class)
        ->set('processing', true)
        ->set('totalToProcess', 1)
        ->call('processNextChunk')
        ->assertOk();

    // The user is still imported -- only the role they were not entitled to
    // confer is dropped, matching UserController::invitationRole().
    expect(User::where('email', 'escalate@example.test')->exists())->toBeTrue()
        ->and(DB::table('model_has_roles')->where('role_id', $ownerRoleId)->count())->toBe(0);
});

it('lets an Owner import a user with the Owner role named in the CSV', function () {
    Queue::fake();
    im007RoleTables();

    $this->actingAsUserWithPermissions(
        ['view crm users', 'create crm users'],
        ['crm_roles' => json_encode(['Owner'])]
    );

    $ownerRoleId = im007MakeRole('Owner');

    session(['crm_user_import_preview' => [im007PreviewRow('new-owner@example.test', 'Owner')]]);

    Livewire::test(AuthzUserImport::class)
        ->set('processing', true)
        ->set('totalToProcess', 1)
        ->call('processNextChunk')
        ->assertOk();

    $imported = User::where('email', 'new-owner@example.test')->first();

    expect($imported)->not->toBeNull()
        ->and(DB::table('model_has_roles')
            ->where('role_id', $ownerRoleId)
            ->where('model_id', $imported->id)
            ->count())->toBe(1);
});

it('drops a host app role named in the CSV', function () {
    Queue::fake();
    im007RoleTables();

    $this->actingAsUserWithPermissions(
        ['view crm users', 'create crm users'],
        ['crm_roles' => json_encode(['Admin'])]
    );

    // crm_role = 0 -- a host application role the CRM has no business handing out.
    $hostRoleId = im007MakeRole('super-admin', crmRole: 0);

    session(['crm_user_import_preview' => [im007PreviewRow('host-role@example.test', 'super-admin')]]);

    Livewire::test(AuthzUserImport::class)
        ->set('processing', true)
        ->set('totalToProcess', 1)
        ->call('processNextChunk')
        ->assertOk();

    expect(User::where('email', 'host-role@example.test')->exists())->toBeTrue()
        ->and(DB::table('model_has_roles')->where('role_id', $hostRoleId)->count())->toBe(0);
});

it('assigns an assignable role named in the CSV', function () {
    Queue::fake();
    im007RoleTables();

    $this->actingAsUserWithPermissions(
        ['view crm users', 'create crm users'],
        ['crm_roles' => json_encode(['Admin'])]
    );

    $editorRoleId = im007MakeRole('Editor');

    session(['crm_user_import_preview' => [im007PreviewRow('editor@example.test', 'Editor')]]);

    Livewire::test(AuthzUserImport::class)
        ->set('processing', true)
        ->set('totalToProcess', 1)
        ->call('processNextChunk')
        ->assertOk();

    $imported = User::where('email', 'editor@example.test')->first();

    expect(DB::table('model_has_roles')
        ->where('role_id', $editorRoleId)
        ->where('model_id', $imported->id)
        ->count())->toBe(1);
});

it('drops an unassignable defaultRole id set on the component', function () {
    Queue::fake();
    im007RoleTables();

    $this->actingAsUserWithPermissions(
        ['view crm users', 'create crm users'],
        ['crm_roles' => json_encode(['Admin'])]
    );

    $ownerRoleId = im007MakeRole('Owner');

    // No CSV role column, so the tampered $defaultRole is the source.
    session(['crm_user_import_preview' => [im007PreviewRow('default-role@example.test')]]);

    Livewire::test(AuthzUserImport::class)
        ->set('defaultRole', (string) $ownerRoleId)
        ->set('processing', true)
        ->set('totalToProcess', 1)
        ->call('processNextChunk')
        ->assertOk();

    expect(User::where('email', 'default-role@example.test')->exists())->toBeTrue()
        ->and(DB::table('model_has_roles')->where('role_id', $ownerRoleId)->count())->toBe(0);
});

it('keeps the Owner role out of the import default-role dropdown for a non-Owner', function () {
    im007RoleTables();

    $this->actingAsUserWithPermissions(
        ['view crm users', 'create crm users'],
        ['crm_roles' => json_encode(['Admin'])]
    );

    im007MakeRole('Owner');
    im007MakeRole('Editor');

    // The real render() builds the dropdown, so this one case uses the unstubbed
    // component via its view data rather than the render stub.
    $roles = collect(Livewire::test(UserImport::class)->viewData('roles'))
        ->pluck('name')
        ->all();

    expect($roles)->toContain('Editor')
        ->and($roles)->not->toContain('Owner');
});
