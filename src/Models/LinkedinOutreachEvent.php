<?php

namespace VentureDrake\LaravelCrm\Models;

use VentureDrake\LaravelCrm\Traits\BelongsToTeams;

class LinkedinOutreachEvent extends Model
{
    use BelongsToTeams;

    public const ACTION_CONNECT_SENT = 'connect_sent';

    public const ACTION_ACCEPTED = 'accepted';

    public const ACTION_DM_SENT = 'dm_sent';

    public const ACTION_REPLY_RECEIVED = 'reply_received';

    public const ACTION_SKIPPED = 'skipped';

    public const ACTION_FAILED = 'failed';

    public const ACTION_CIRCUIT_BREAKER_TRIPPED = 'circuit_breaker_tripped';

    public const ACTIONS = [
        self::ACTION_CONNECT_SENT,
        self::ACTION_ACCEPTED,
        self::ACTION_DM_SENT,
        self::ACTION_REPLY_RECEIVED,
        self::ACTION_SKIPPED,
        self::ACTION_FAILED,
        self::ACTION_CIRCUIT_BREAKER_TRIPPED,
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'step' => 'integer',
        'payload' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function getTable()
    {
        return config('laravel-crm.db_table_prefix').'linkedin_outreach_events';
    }

    public function campaign()
    {
        return $this->belongsTo(LinkedinCampaign::class, 'linkedin_campaign_id');
    }

    public function recipient()
    {
        return $this->belongsTo(LinkedinCampaignRecipient::class, 'linkedin_campaign_recipient_id');
    }
}
