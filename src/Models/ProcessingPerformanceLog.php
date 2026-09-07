<?php

namespace VentureDrake\LaravelCrm\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;

class ProcessingPerformanceLog extends EloquentModel
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'latency_ms' => 'float',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function getTable()
    {
        return config('laravel-crm.db_table_prefix').'processing_performance_logs';
    }
}
