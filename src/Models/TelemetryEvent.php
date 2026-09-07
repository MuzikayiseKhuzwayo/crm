<?php

namespace VentureDrake\LaravelCrm\Models;

use VentureDrake\LaravelCrm\Traits\BelongsToTeams;

class TelemetryEvent extends Model
{
    use BelongsToTeams;

    protected $guarded = ['id'];

    protected $casts = [
        'payload' => 'array',
        'metric_value' => 'float',
        'recorded_at' => 'datetime',
    ];

    public function getTable()
    {
        return config('laravel-crm.db_table_prefix').'telemetry_events';
    }
}
