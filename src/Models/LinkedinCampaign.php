<?php

namespace VentureDrake\LaravelCrm\Models;

use App\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use VentureDrake\LaravelCrm\Traits\BelongsToTeams;
use VentureDrake\LaravelCrm\Traits\SearchFilters;

class LinkedinCampaign extends Model
{
    use BelongsToTeams;
    use SearchFilters;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_CIRCUIT_BROKEN = 'circuit_broken';

    public const STATUS_COMPLETED = 'completed';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_PAUSED,
        self::STATUS_CIRCUIT_BROKEN,
        self::STATUS_COMPLETED,
    ];

    protected $guarded = ['id'];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'daily_connect_limit' => 15,
        'daily_dm_limit' => 30,
        'weekly_connect_limit' => 80,
        'min_delay_seconds' => 180,
        'max_delay_seconds' => 420,
        'active_days' => '[1,2,3,4,5]',
        'active_start_hour' => 8,
        'active_end_hour' => 18,
        'timezone' => 'Africa/Johannesburg',
        'total_recipients' => 0,
        'connects_sent_count' => 0,
        'accepted_count' => 0,
        'dms_sent_count' => 0,
        'replied_count' => 0,
        'failed_count' => 0,
    ];

    protected $casts = [
        'sequence_steps' => 'array',
        'active_days' => 'array',
        'daily_connect_limit' => 'integer',
        'daily_dm_limit' => 'integer',
        'weekly_connect_limit' => 'integer',
        'min_delay_seconds' => 'integer',
        'max_delay_seconds' => 'integer',
        'active_start_hour' => 'integer',
        'active_end_hour' => 'integer',
        'circuit_broken_at' => 'datetime',
        'last_worker_heartbeat_at' => 'datetime',
        'total_recipients' => 'integer',
        'connects_sent_count' => 'integer',
        'accepted_count' => 'integer',
        'dms_sent_count' => 'integer',
        'replied_count' => 'integer',
        'failed_count' => 'integer',
    ];

    protected $searchable = [
        'name',
    ];

    protected $filterable = [
        'status',
        'user_owner_id',
    ];

    public function getSearchable()
    {
        return $this->searchable;
    }

    public function getTable()
    {
        return config('laravel-crm.db_table_prefix').'linkedin_campaigns';
    }

    public function recipients()
    {
        return $this->hasMany(LinkedinCampaignRecipient::class, 'linkedin_campaign_id');
    }

    public function events()
    {
        return $this->hasMany(LinkedinOutreachEvent::class, 'linkedin_campaign_id');
    }

    public function ownerUser()
    {
        return $this->belongsTo(User::class, 'user_owner_id');
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'user_created_id');
    }

    public function updatedByUser()
    {
        return $this->belongsTo(User::class, 'user_updated_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isCircuitBroken(): bool
    {
        return $this->status === self::STATUS_CIRCUIT_BROKEN;
    }

    public function acceptanceRate(): float
    {
        if ($this->connects_sent_count == 0) {
            return 0.0;
        }

        return round(($this->accepted_count / $this->connects_sent_count) * 100, 1);
    }

    public function replyRate(): float
    {
        if ($this->total_recipients == 0) {
            return 0.0;
        }

        return round(($this->replied_count / $this->total_recipients) * 100, 1);
    }
}
