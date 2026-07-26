<?php

namespace VentureDrake\LaravelCrm\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use VentureDrake\LaravelCrm\Models\UserInvitation;

class UserInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected UserInvitation $invitation;

    public function __construct(UserInvitation $invitation)
    {
        $this->invitation = $invitation;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $appName = config('app.name');
        $inviter = $this->resolveInviterName();
        $teamName = $this->resolveTeamName();
        $roleName = $this->resolveRoleName();

        $url = route('laravel-crm.users.invitations.accept', $this->invitation->code);

        $message = (new MailMessage)
            ->subject(trans('laravel-crm::lang.user_invitation_subject', ['app' => $appName]))
            ->greeting(trans('laravel-crm::lang.user_invitation_greeting'))
            ->line(trans('laravel-crm::lang.user_invitation_intro', [
                'inviter' => $inviter,
                'app' => $appName,
            ]));

        if ($teamName !== null) {
            $message->line(trans('laravel-crm::lang.user_invitation_team_line', ['team' => $teamName]));
        }

        if ($roleName !== null) {
            $message->line(trans('laravel-crm::lang.user_invitation_role_line', ['role' => $roleName]));
        }

        if ($this->invitation->expires_at !== null) {
            $message->line(trans('laravel-crm::lang.user_invitation_expires_line', [
                'date' => $this->invitation->expires_at->toDayDateTimeString(),
            ]));
        } else {
            $message->line(trans('laravel-crm::lang.user_invitation_no_expiry_line'));
        }

        return $message
            ->action(trans('laravel-crm::lang.user_invitation_action'), $url)
            ->line(trans('laravel-crm::lang.user_invitation_outro'));
    }

    protected function resolveInviterName(): string
    {
        $inviterId = $this->invitation->invited_by ?? null;

        if ($inviterId === null) {
            return config('app.name');
        }

        $userModel = config('auth.providers.users.model');

        if (! $userModel || ! class_exists($userModel)) {
            return config('app.name');
        }

        $inviter = $userModel::find($inviterId);

        return $inviter?->name ?? config('app.name');
    }

    protected function resolveTeamName(): ?string
    {
        $teamId = $this->invitation->team_id ?? null;

        if ($teamId === null) {
            return null;
        }

        // team_id references the host teams table (same ID used for
        // current_team_id, the team_user pivot, and Spatie permissions),
        // not crm_teams. Try the configured host model first, then
        // App\Models\Team, and finally read the row directly.
        $hostTeamModel = config('laravel-crm.host_team_model');

        if ($hostTeamModel && class_exists($hostTeamModel)) {
            return $hostTeamModel::query()->whereKey($teamId)->value('name');
        }

        if (class_exists(\App\Models\Team::class)) {
            return \App\Models\Team::query()->whereKey($teamId)->value('name');
        }

        return DB::table('teams')->where('id', $teamId)->value('name');
    }

    protected function resolveRoleName(): ?string
    {
        $roleId = $this->invitation->role_id ?? null;

        if ($roleId === null) {
            return null;
        }

        if (! class_exists(Role::class)) {
            return null;
        }

        return Role::query()->whereKey($roleId)->value('name');
    }
}
