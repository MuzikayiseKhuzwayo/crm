<?php

namespace VentureDrake\LaravelCrm\Models;

use App\User;
use VentureDrake\LaravelCrm\Traits\BelongsToTeams;

class DealDerisking extends Model
{
    use BelongsToTeams;

    protected $guarded = ['id'];

    protected $casts = [
        'commercial_thesis_validated' => 'boolean',
        'loi_signed_at' => 'datetime',
        'pilot_converted_at' => 'datetime',
    ];

    public function getTable()
    {
        return config('laravel-crm.db_table_prefix').'deal_derisking';
    }

    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'user_created_id');
    }

    public function updatedByUser()
    {
        return $this->belongsTo(User::class, 'user_updated_id');
    }
}
