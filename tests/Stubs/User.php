<?php

namespace VentureDrake\LaravelCrm\Tests\Stubs;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use VentureDrake\LaravelCrm\Models\Role;
use VentureDrake\LaravelCrm\Traits\HasCrmAddresses;
use VentureDrake\LaravelCrm\Traits\HasCrmEmails;
use VentureDrake\LaravelCrm\Traits\HasCrmPhones;
use VentureDrake\LaravelCrm\Traits\HasCrmTeams;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasCrmAddresses;
    use HasCrmEmails;
    use HasCrmPhones;
    use HasCrmTeams;
    use HasFactory;
    use Notifiable;

    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function hasPermissionTo($permission, $guardName = null): bool
    {
        $raw = $this->attributes['crm_permissions'] ?? null;

        if ($raw === null) {
            return true;
        }

        $list = is_array($raw) ? $raw : (json_decode($raw, true) ?: []);

        $name = is_object($permission) ? ($permission->name ?? '') : (string) $permission;

        return in_array($name, $list, true);
    }

    /**
     * Spatie registers a Gate::before hook that only consults the user when
     * checkPermissionTo() exists (PermissionRegistrar::registerPermissions).
     * Without this, `@can('edit crm notes')` in a Blade view resolves to false
     * for every stub user, so permission-string gates could never be exercised.
     */
    public function checkPermissionTo($permission, $guardName = null): bool
    {
        return $this->hasPermissionTo($permission, $guardName);
    }

    /**
     * Mirrors hasPermissionTo(): permissive when `crm_roles` is unset so the
     * ~800 pre-existing tests keep passing, and explicit when a test needs to
     * exercise a role-gated branch (e.g. the Owner escalation guard).
     */
    public function hasRole($roles, $guard = null): bool
    {
        $raw = $this->attributes['crm_roles'] ?? null;

        if ($raw === null) {
            return true;
        }

        $held = is_array($raw) ? $raw : (json_decode($raw, true) ?: []);

        foreach ((array) $roles as $role) {
            $name = is_object($role) ? ($role->name ?? '') : (string) $role;

            if (in_array($name, $held, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Minimal stand-ins for the Spatie HasRoles relation + mutators. Enough for
     * the CRM's `$user->roles()->where('crm_role', 1)->first()` /
     * assignRole() / removeRole() flow; tests that use them create the
     * `roles` and `model_has_roles` tables on demand.
     */
    public function roles()
    {
        return $this->morphToMany(
            Role::class,
            'model',
            'model_has_roles',
            'model_id',
            'role_id'
        );
    }

    public function assignRole($role)
    {
        $this->roles()->syncWithoutDetaching([is_object($role) ? $role->getKey() : $role]);

        return $this;
    }

    public function removeRole($role)
    {
        $this->roles()->detach(is_object($role) ? $role->getKey() : $role);

        return $this;
    }

    public function getCurrentTeamAttribute()
    {
        if (array_key_exists('currentTeam', $this->relations)) {
            return $this->relations['currentTeam'];
        }

        $id = $this->attributes['current_team_id'] ?? null;

        return $id ? (object) ['id' => (int) $id, 'name' => 'Team '.(int) $id, 'user_id' => 0] : null;
    }

    public function allTeams()
    {
        $raw = $this->attributes['team_ids'] ?? null;

        if (! $raw) {
            return collect();
        }

        $ids = is_array($raw) ? $raw : (json_decode($raw, true) ?: []);

        return collect($ids)->map(fn ($id) => (object) ['id' => (int) $id, 'name' => 'Team '.(int) $id, 'user_id' => 0]);
    }
}
