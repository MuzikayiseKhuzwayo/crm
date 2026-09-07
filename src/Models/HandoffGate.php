<?php

namespace VentureDrake\LaravelCrm\Models;

use App\User;
use VentureDrake\LaravelCrm\Traits\BelongsToTeams;

class HandoffGate extends Model
{
    use BelongsToTeams;

    protected $guarded = ['id'];

    protected $casts = [
        'cleared_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function getTable()
    {
        return config('laravel-crm.db_table_prefix').'handoff_gates';
    }

    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }

    public function clearedByUser()
    {
        return $this->belongsTo(User::class, 'cleared_by_user_id');
    }

    public function isCleared(): bool
    {
        return in_array($this->status, ['approved', 'waived']);
    }
}
