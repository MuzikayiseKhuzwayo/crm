<?php

namespace VentureDrake\LaravelCrm\Livewire\Users;

use App\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use VentureDrake\LaravelCrm\Http\Rules\AssignableRole;
use VentureDrake\LaravelCrm\Livewire\Users\Traits\HasUserCommon;
use VentureDrake\LaravelCrm\Models\Role;

class UserCreate extends Component
{
    use AuthorizesRequests, HasUserCommon;

    public $layout = 'full';

    protected function rules()
    {
        return [
            'name' => 'required|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => ['nullable', new AssignableRole],
        ];
    }

    public function mount()
    {
        $this->mountCommon();

        $this->addPhone();

        $this->addAddress();
    }

    public function save()
    {
        $this->authorize('create', User::class);

        $this->validate();

        // Resolve and vet the role *before* the user row is written. The
        // AssignableRole rule above already rejects an unassignable role, so this
        // is belt-and-braces -- but it has to abort before the forceCreate() or a
        // 403 leaves an orphaned, role-less user behind and burns the email
        // address against the unique index.
        $role = $this->role ? Role::assignable()->find($this->role) : null;

        abort_if($role && $role->name === 'Owner' && ! auth()->user()->hasRole('Owner'), 403);

        $user = User::forceCreate([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'crm_access' => $this->crm_access,
        ]);

        if ($role) {
            if ($removeRole = $user->roles()->where('crm_role', 1)->first()) { // THIS COULD BE A BUG
                $user->removeRole($removeRole);
            }

            $user->assignRole($role);
        }

        $this->updateUserPhones($user, $this->phones);
        $this->updateUserAddresses($user, $this->addresses);

        if (config('laravel-crm.teams')) {
            if ($team = auth()->user()->currentTeam) {
                DB::table('team_user')->insert([
                    'team_id' => $team->id,
                    'user_id' => $user->id,
                    'role' => 'editor', // Default Jetstream role
                ]);

                $user->forceFill([
                    'current_team_id' => $team->id,
                ])->save();
            }
        }

        if ($this->userTeams) {
            $user->crmTeams()->sync($this->userTeams);
        } else {
            $user->crmTeams()->sync([]);
        }

        $this->success(
            ucfirst(trans('laravel-crm::lang.user_created')),
            redirectTo: route('laravel-crm.users.index')
        );
    }

    public function render()
    {
        return view('laravel-crm::livewire.users.user-create');
    }
}
