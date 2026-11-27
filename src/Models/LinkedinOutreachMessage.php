<?php

namespace VentureDrake\LaravelCrm\Models;

use VentureDrake\LaravelCrm\Traits\BelongsToTeams;

class LinkedinOutreachMessage extends Model
{
    use BelongsToTeams;

    public const DIRECTION_OUTBOUND = 'outbound';

    public const DIRECTION_INBOUND = 'inbound';

    public const SOURCE_WORKER = 'worker';

    public const SOURCE_INBOX_SYNC = 'inbox_sync';

    public const SOURCE_THREAD_RECONCILE = 'thread_reconcile';

    protected $guarded = ['id'];

    protected $casts = [
        'step' => 'integer',
        'occurred_at' => 'datetime',
    ];

    public function getTable()
    {
        return config('laravel-crm.db_table_prefix').'linkedin_outreach_messages';
    }

    public function campaign()
    {
        return $this->belongsTo(LinkedinCampaign::class, 'linkedin_campaign_id');
    }

    public function recipient()
    {
        return $this->belongsTo(LinkedinCampaignRecipient::class, 'linkedin_campaign_recipient_id');
    }

    public static function stripSenderPrefix(string $body): string
    {
        return trim((string) preg_replace('/^\s*you\s*:\s*/i', '', trim($body)));
    }

    public static function normalizeForHash(string $body): string
    {
        $cleaned = self::stripSenderPrefix($body);
        $cleaned = mb_strtolower($cleaned, 'UTF-8');
        $cleaned = (string) preg_replace('#https?://\S+|www\.\S+#i', '', $cleaned);
        $cleaned = (string) preg_replace('/[^\p{L}\p{N}]+/u', '', $cleaned);

        return mb_substr($cleaned, 0, 160, 'UTF-8');
    }

    public static function computeContentHash(string $body): string
    {
        return hash('sha256', self::normalizeForHash($body));
    }
}
