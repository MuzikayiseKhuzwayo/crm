<?php

namespace VentureDrake\LaravelCrm\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use VentureDrake\LaravelCrm\Traits\BelongsToTeams;

class UserInvitation extends Model
{
    use BelongsToTeams;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'last_sent_at' => 'datetime',
    ];

    public function getTable()
    {
        return config('laravel-crm.db_table_prefix').'user_invitations';
    }

    public function getRouteKeyName()
    {
        return 'code';
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function invitedByUser()
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'invited_by');
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return ! $this->isAccepted() && ! $this->isExpired();
    }

    public function isValid(): bool
    {
        return $this->isPending();
    }
}
