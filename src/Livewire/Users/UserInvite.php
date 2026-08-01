<?php

namespace VentureDrake\LaravelCrm\Livewire\Users;

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Component;
use Mary\Traits\Toast;
use VentureDrake\LaravelCrm\Models\Role;
use VentureDrake\LaravelCrm\Models\UserInvitation;
use VentureDrake\LaravelCrm\Notifications\UserInvitationNotification;

class UserInvite extends Component
{
    use Toast;

    public $layout = 'full';

    public ?string $email = null;

    public $role_id = null;

    public array $roles = [
        ['id' => '', 'name' => ''],
    ];

    public function mount(): void
    {
        // Role::assignable() is the single source of truth for "roles this caller
        // may hand out" -- the dropdown and the validation rule must not diverge.
        foreach (Role::assignable()->get() as $role) {
            $this->roles[] = [
                'id' => $role->id,
                'name' => $role->name,
            ];
        }
    }

    protected function rules(): array
    {
        return [
            'email' => [
                'required',
                'email',
                'max:255',
                function (string $attribute, $value, $fail) {
                    $teamId = $this->currentTeamId();

                    $userExists = User::query()
                        ->where('email', $value)
                        ->exists();

                    if ($userExists) {
                        $fail(ucfirst(__('laravel-crm::lang.user_invitation_email_taken')));

                        return;
                    }

                    $pendingInvite = UserInvitation::query()
                        ->where('email', $value)
                        ->whereNull('accepted_at')
                        ->where(function ($query) {
                            $query->whereNull('expires_at')
                                ->orWhere('expires_at', '>', now());
                        })
                        ->when(config('laravel-crm.teams'), function ($query) use ($teamId) {
                            return $query->where('team_id', $teamId);
                        })
                        ->exists();

                    if ($pendingInvite) {
                        $fail(ucfirst(__('laravel-crm::lang.user_invitation_pending')));
                    }
                },
            ],
            'role_id' => [
                'required',
                function (string $attribute, $value, $fail) {
                    $exists = Role::assignable()
                        ->whereKey($value)
                        ->exists();

                    if (! $exists) {
                        $fail(ucfirst(__('laravel-crm::lang.user_invitation_role_invalid')));
                    }
                },
            ],
        ];
    }

    public function save()
    {
        $data = $this->validate();

        $invitation = DB::transaction(function () use ($data) {
            $invitation = UserInvitation::create([
                'email' => $data['email'],
                'role_id' => $data['role_id'],
                'team_id' => $this->currentTeamId(),
                'invited_by' => auth()->id(),
                'last_sent_at' => now(),
            ]);

            Notification::route('mail', $data['email'])
                ->notify(new UserInvitationNotification($invitation));

            return $invitation;
        });

        $this->success(
            ucfirst(__('laravel-crm::lang.user_invitation_sent')),
            redirectTo: route('laravel-crm.users.index')
        );

        return $invitation;
    }

    protected function currentTeamId(): ?int
    {
        if (! config('laravel-crm.teams')) {
            return null;
        }

        return auth()->user()?->currentTeam?->id;
    }

    public function render()
    {
        return view('laravel-crm::livewire.users.user-invite');
    }
}
