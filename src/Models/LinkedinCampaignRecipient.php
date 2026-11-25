<?php

namespace VentureDrake\LaravelCrm\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use VentureDrake\LaravelCrm\Traits\BelongsToTeams;
use VentureDrake\LaravelCrm\Traits\SearchFilters;

class LinkedinCampaignRecipient extends Model
{
    use BelongsToTeams;
    use SearchFilters;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_LEASED = 'leased';

    public const STATUS_CONNECT_SENT = 'connect_sent';

    public const STATUS_CONNECTED = 'connected';

    public const STATUS_IN_SEQUENCE = 'in_sequence';

    public const STATUS_REPLIED = 'replied';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_QUEUED,
        self::STATUS_LEASED,
        self::STATUS_CONNECT_SENT,
        self::STATUS_CONNECTED,
        self::STATUS_IN_SEQUENCE,
        self::STATUS_REPLIED,
        self::STATUS_COMPLETED,
        self::STATUS_SKIPPED,
        self::STATUS_FAILED,
    ];

    public const ACTIVE_STATUSES = [
        self::STATUS_QUEUED,
        self::STATUS_LEASED,
        self::STATUS_CONNECT_SENT,
        self::STATUS_CONNECTED,
        self::STATUS_IN_SEQUENCE,
    ];

    public const ACTIONABLE_STATUSES = [
        self::STATUS_QUEUED,
        self::STATUS_CONNECTED,
        self::STATUS_IN_SEQUENCE,
    ];

    public const TERMINAL_STATUSES = [
        self::STATUS_REPLIED,
        self::STATUS_COMPLETED,
        self::STATUS_SKIPPED,
        self::STATUS_FAILED,
    ];

    protected $guarded = ['id'];

    protected $attributes = [
        'status' => self::STATUS_QUEUED,
        'current_step' => 0,
        'attempt_count' => 0,
    ];

    protected $casts = [
        'current_step' => 'integer',
        'attempt_count' => 'integer',
        'next_action_at' => 'datetime',
        'leased_at' => 'datetime',
        'lease_expires_at' => 'datetime',
        'connect_sent_at' => 'datetime',
        'connected_at' => 'datetime',
        'last_dm_sent_at' => 'datetime',
        'replied_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $searchable = [
        'linkedin_url',
        'normalized_linkedin_slug',
    ];

    protected $filterable = [
        'status',
        'linkedin_campaign_id',
    ];

    public function getSearchable()
    {
        return $this->searchable;
    }

    public function getTable()
    {
        return config('laravel-crm.db_table_prefix').'linkedin_campaign_recipients';
    }

    public function campaign()
    {
        return $this->belongsTo(LinkedinCampaign::class, 'linkedin_campaign_id');
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function person()
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    public function events()
    {
        return $this->hasMany(LinkedinOutreachEvent::class, 'linkedin_campaign_recipient_id');
    }

    /**
     * Strip query params, fragments, and trailing slashes to extract the canonical /in/<slug> identifier.
     */
    public static function normalizeLinkedinUrl(string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $url)) {
            $url = str_starts_with($url, '/in/')
                ? 'https://www.linkedin.com'.$url
                : 'https://'.$url;
        }

        $parsed = parse_url($url);

        if ($parsed === false || empty($parsed['host']) || empty($parsed['path'])) {
            return null;
        }

        $host = strtolower($parsed['host']);
        if ($host !== 'linkedin.com' && ! str_ends_with($host, '.linkedin.com')) {
            return null;
        }

        $path = trim($parsed['path'], '/');

        if (! preg_match('#^in/([^/]+)#i', $path, $matches)) {
            return null;
        }

        $slug = strtolower(trim(rawurldecode($matches[1])));

        return $slug !== '' ? $slug : null;
    }

    public function scopeDueForAction(Builder $query, ?CarbonInterface $now = null): Builder
    {
        $at = $now ?? now();

        return $query
            ->whereIn('status', self::ACTIONABLE_STATUSES)
            ->where(function (Builder $q) use ($at) {
                $q->whereNull('next_action_at')
                    ->orWhere('next_action_at', '<=', $at);
            })
            ->where(function (Builder $q) use ($at) {
                $q->whereNull('lease_expires_at')
                    ->orWhere('lease_expires_at', '<=', $at);
            });
    }

    public function scopeExpiredLeases(Builder $query, ?CarbonInterface $now = null): Builder
    {
        $at = $now ?? now();

        return $query
            ->where('status', self::STATUS_LEASED)
            ->whereNotNull('lease_expires_at')
            ->where('lease_expires_at', '<=', $at);
    }

    public function scopeActiveInSequence(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }
}
