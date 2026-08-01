<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use VentureDrake\LaravelCrm\Livewire\Teams\TeamIndex;
use VentureDrake\LaravelCrm\Livewire\Users\UserCreate;
use VentureDrake\LaravelCrm\Livewire\Users\UserIndex;
use VentureDrake\LaravelCrm\Models\Role;
use VentureDrake\LaravelCrm\Models\Team;
use VentureDrake\LaravelCrm\Support\TeamMembership;
use VentureDrake\LaravelCrm\Tests\Stubs\User;

/**
 * Render-stub subclasses -- see ChatAuthorizationTest for the rationale. Only
 * render() is replaced; every guarded action method runs for real against the
 * real policies.
 */
class AuthzUserIndex extends UserIndex
{
    /** Toast titles captured so tests can assert on the message, not the JS payload. */
    public array $errorToasts = [];

    public function render()
    {
        return '<div></div>';
    }

    public function error(
        string $title,
        ?string $description = null,
        ?string $position = null,
        string $icon = 'o-x-circle',
        string $css = 'alert-error',
        int $timeout = 3000,
        ?string $redirectTo = null,
        bool $noProgress = false,
        ?string $progressClass = null,
    ) {
        $this->errorToasts[] = $title;
    }
}
class AuthzUserCreate extends UserCreate
{
    public function render()
    {
        return '<div></div>';
    }
}
class AuthzTeamIndex extends TeamIndex
{
    public function render()
    {
        return '<div></div>';
    }
}

if (! function_exists('us005RoleTables')) {
    function us005RoleTables(): void
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
    }
}

if (! function_exists('us005MakeRole')) {
    function us005MakeRole(string $name, ?int $teamId = null, int $crmRole = 1): int
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

if (! function_exists('us005MakeUser')) {
    function us005MakeUser(string $email, array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Other user',
            'email' => $email,
            'password' => bcrypt('secret-password'),
            'crm_access' => 1,
        ], $attributes));
    }
}

beforeEach(function () {
    us005RoleTables();
    DB::table('roles')->delete();
    DB::table('model_has_roles')->delete();
    DB::table('team_user')->delete();
});

/*
 * ---------------------------------------------------------------------------
 * TeamMembership -- the shared predicate behind the delete guard.
 * ---------------------------------------------------------------------------
 */

it('treats every user as in-team when teams are disabled', function () {
    config(['laravel-crm.teams' => false]);

    $this->actingAsUser();

    // No allTeams data at all -- still true, because there is no boundary.
    expect(TeamMembership::inCurrentTeam(us005MakeUser('nobody@example.test')))->toBeTrue();
});

it('fails closed when teams are enabled but no current team resolves', function () {
    config(['laravel-crm.teams' => true]);

    // current_team_id left null, so currentTeam is null.
    $this->actingAsUser();

    $other = us005MakeUser('other@example.test', ['team_ids' => json_encode([1])]);

    expect(TeamMembership::inCurrentTeam($other))->toBeFalse();
});

it('resolves membership from allTeams when teams are enabled', function () {
    config(['laravel-crm.teams' => true]);

    $this->actingAsUser(['current_team_id' => 1, 'team_ids' => json_encode([1])]);

    $sameTeam = us005MakeUser('same@example.test', ['team_ids' => json_encode([1, 2])]);
    $otherTeam = us005MakeUser('other@example.test', ['team_ids' => json_encode([2])]);

    expect(TeamMembership::inCurrentTeam($sameTeam))->toBeTrue()
        ->and(TeamMembership::inCurrentTeam($otherTeam))->toBeFalse();
});

/*
 * ---------------------------------------------------------------------------
 * UserIndex::delete
 * ---------------------------------------------------------------------------
 */

it('forbids deleting a user without the delete permission', function () {
    $this->actingAsUserWithPermissions(['view crm users']);

    $target = us005MakeUser('target@example.test');

    Livewire::test(AuthzUserIndex::class)
        ->call('delete', $target->id)
        ->assertForbidden();

    expect(User::find($target->id))->not->toBeNull();
});

it('403s when deleting a user outside the current team and the user survives', function () {
    config(['laravel-crm.teams' => true]);

    $this->actingAsUserWithPermissions(
        ['delete crm users'],
        ['current_team_id' => 1, 'team_ids' => json_encode([1])]
    );

    $foreign = us005MakeUser('foreign@example.test', ['team_ids' => json_encode([2])]);

    Livewire::test(AuthzUserIndex::class)
        ->call('delete', $foreign->id)
        ->assertForbidden();

    expect(User::find($foreign->id))->not->toBeNull();
});

it('deletes a user inside the current team', function () {
    config(['laravel-crm.teams' => true]);

    $this->actingAsUserWithPermissions(
        ['delete crm users'],
        ['current_team_id' => 1, 'team_ids' => json_encode([1])]
    );

    $teammate = us005MakeUser('teammate@example.test', ['team_ids' => json_encode([1])]);

    Livewire::test(AuthzUserIndex::class)
        ->call('delete', $teammate->id)
        ->assertOk();

    expect(User::find($teammate->id))->toBeNull();
});

it('blocks self-deletion with an error toast rather than a 403', function () {
    $me = $this->actingAsUserWithPermissions(['delete crm users']);

    $component = Livewire::test(AuthzUserIndex::class)
        ->call('delete', $me->id)
        ->assertOk();

    expect(User::find($me->id))->not->toBeNull()
        ->and($component->get('errorToasts'))
        ->toBe([ucfirst(trans('laravel-crm::lang.user_cannot_delete_self'))]);
});

/*
 * ---------------------------------------------------------------------------
 * UserIndex::users() -- the listing must not show rows the delete guard 403s on.
 * ---------------------------------------------------------------------------
 */

it('scopes the users listing to the current team when teams are enabled', function () {
    config(['laravel-crm.teams' => true]);

    $me = $this->actingAsUserWithPermissions(
        ['view crm users'],
        ['current_team_id' => 1, 'team_ids' => json_encode([1])]
    );

    $teammate = us005MakeUser('teammate@example.test', ['team_ids' => json_encode([1])]);
    $foreign = us005MakeUser('foreign@example.test', ['team_ids' => json_encode([2])]);

    foreach ([[1, $me->id], [1, $teammate->id], [2, $foreign->id]] as [$teamId, $userId]) {
        DB::table('team_user')->insert(['team_id' => $teamId, 'user_id' => $userId, 'role' => 'editor']);
    }

    $ids = Livewire::test(AuthzUserIndex::class)->instance()->users()->pluck('id')->all();

    expect($ids)->toContain($me->id)
        ->and($ids)->toContain($teammate->id)
        ->and($ids)->not->toContain($foreign->id);
});

it('lists every user when teams are disabled', function () {
    config(['laravel-crm.teams' => false]);

    $me = $this->actingAsUserWithPermissions(['view crm users']);
    $other = us005MakeUser('other@example.test');

    $ids = Livewire::test(AuthzUserIndex::class)->instance()->users()->pluck('id')->all();

    expect($ids)->toContain($me->id)->and($ids)->toContain($other->id);
});

/*
 * ---------------------------------------------------------------------------
 * TeamIndex::delete
 * ---------------------------------------------------------------------------
 */

it('forbids deleting a team without the delete permission', function () {
    $this->actingAsUserWithPermissions(['view crm teams']);

    $team = Team::create(['name' => 'Team A', 'user_id' => 1]);

    Livewire::test(AuthzTeamIndex::class)
        ->call('delete', $team->id)
        ->assertForbidden();

    expect(Team::find($team->id))->not->toBeNull();
});

it('deletes a team with the delete permission', function () {
    $this->actingAsUserWithPermissions(['delete crm teams']);

    $team = Team::create(['name' => 'Team A', 'user_id' => 1]);

    Livewire::test(AuthzTeamIndex::class)
        ->call('delete', $team->id)
        ->assertOk();

    expect(Team::find($team->id))->toBeNull();
});

it('imports the Url attribute so the TeamIndex query-string bindings are live', function () {
    $source = file_get_contents((new ReflectionClass(TeamIndex::class))->getFileName());

    expect($source)->toContain('use Livewire\Attributes\Url;');
});

/*
 * ---------------------------------------------------------------------------
 * Role::assignable()
 * ---------------------------------------------------------------------------
 */

it('keeps globally seeded roles assignable in teams mode', function () {
    config(['laravel-crm.teams' => true]);

    $this->actingAsUser(['current_team_id' => 1, 'team_ids' => json_encode([1])]);

    // The seeder writes Owner/Admin/Manager/Employee with team_id => null even
    // when teams are on; a plain team_id match would return an empty set.
    $globalRoleId = us005MakeRole('Owner');
    $ownTeamRoleId = us005MakeRole('Team 1 Editor', teamId: 1);
    $foreignRoleId = us005MakeRole('Team 2 Editor', teamId: 2);
    $nonCrmRoleId = us005MakeRole('Host App Role', crmRole: 0);

    $assignable = Role::assignable()->pluck('id')->all();

    expect($assignable)->toContain($globalRoleId)
        ->and($assignable)->toContain($ownTeamRoleId)
        ->and($assignable)->not->toContain($foreignRoleId)
        ->and($assignable)->not->toContain($nonCrmRoleId);
});

it('keeps the Owner role assignable so ownership transfer still works', function () {
    config(['laravel-crm.teams' => false]);

    $this->actingAsUser();

    $ownerRoleId = us005MakeRole('Owner');

    expect(Role::assignable()->pluck('id')->all())->toContain($ownerRoleId);
});

/*
 * ---------------------------------------------------------------------------
 * Role assignment -- cross-team role ids and Owner escalation.
 * ---------------------------------------------------------------------------
 */

it('rejects a role belonging to another team and creates no user', function () {
    config(['laravel-crm.teams' => true]);

    $this->actingAsUserWithPermissions(
        ['create crm users'],
        ['current_team_id' => 1, 'team_ids' => json_encode([1])]
    );

    $foreignRoleId = us005MakeRole('Team 2 Editor', teamId: 2);

    Livewire::test(AuthzUserCreate::class)
        ->set('name', 'New user')
        ->set('email', 'new-user@example.test')
        ->set('password', 'secret-password')
        ->set('password_confirmation', 'secret-password')
        ->set('crm_access', true)
        ->set('role', $foreignRoleId)
        ->call('save')
        ->assertHasErrors('role');

    expect(User::where('email', 'new-user@example.test')->exists())->toBeFalse()
        ->and(DB::table('model_has_roles')->where('role_id', $foreignRoleId)->count())->toBe(0);
});

it('403s when a non-Owner assigns the Owner role', function () {
    $this->actingAsUserWithPermissions(
        ['create crm users'],
        ['crm_roles' => json_encode(['Admin'])]
    );

    $ownerRoleId = us005MakeRole('Owner');

    Livewire::test(AuthzUserCreate::class)
        ->set('name', 'New user')
        ->set('email', 'escalate@example.test')
        ->set('password', 'secret-password')
        ->set('password_confirmation', 'secret-password')
        ->set('crm_access', true)
        ->set('role', $ownerRoleId)
        ->call('save')
        ->assertForbidden();

    expect(DB::table('model_has_roles')->where('role_id', $ownerRoleId)->count())->toBe(0);
});

it('lets an Owner assign the Owner role', function () {
    $this->actingAsUserWithPermissions(
        ['create crm users'],
        ['crm_roles' => json_encode(['Owner'])]
    );

    $ownerRoleId = us005MakeRole('Owner');

    Livewire::test(AuthzUserCreate::class)
        ->set('name', 'New owner')
        ->set('email', 'new-owner@example.test')
        ->set('password', 'secret-password')
        ->set('password_confirmation', 'secret-password')
        ->set('crm_access', true)
        ->set('role', $ownerRoleId)
        ->call('save')
        ->assertHasNoErrors();

    $created = User::where('email', 'new-owner@example.test')->first();

    expect($created)->not->toBeNull()
        ->and(DB::table('model_has_roles')
            ->where('role_id', $ownerRoleId)
            ->where('model_id', $created->id)
            ->count())->toBe(1);
});

it('assigns a non-Owner role without tripping the escalation guard', function () {
    $this->actingAsUserWithPermissions(
        ['create crm users'],
        ['crm_roles' => json_encode(['Admin'])]
    );

    $editorRoleId = us005MakeRole('Editor');

    Livewire::test(AuthzUserCreate::class)
        ->set('name', 'New editor')
        ->set('email', 'new-editor@example.test')
        ->set('password', 'secret-password')
        ->set('password_confirmation', 'secret-password')
        ->set('crm_access', true)
        ->set('role', $editorRoleId)
        ->call('save')
        ->assertHasNoErrors();

    $created = User::where('email', 'new-editor@example.test')->first();

    expect($created)->not->toBeNull()
        ->and(DB::table('model_has_roles')
            ->where('role_id', $editorRoleId)
            ->where('model_id', $created->id)
            ->count())->toBe(1);
});
