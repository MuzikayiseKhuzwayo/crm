<?php

namespace VentureDrake\LaravelCrm\Models;

use App\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use VentureDrake\LaravelCrm\Traits\BelongsToTeams;
use VentureDrake\LaravelCrm\Traits\SearchFilters;

class LinkedinTemplate extends Model
{
    use BelongsToTeams;
    use SearchFilters;
    use SoftDeletes;

    public const TYPE_CONNECT_NOTE = 'connect_note';

    public const TYPE_DM = 'dm';

    public const TYPE_FOLLOW_UP = 'follow_up';

    public const TYPES = [
        self::TYPE_CONNECT_NOTE,
        self::TYPE_DM,
        self::TYPE_FOLLOW_UP,
    ];

    protected $guarded = ['id'];

    protected $attributes = [
        'type' => self::TYPE_CONNECT_NOTE,
    ];

    protected $searchable = [
        'name',
        'body',
    ];

    protected $filterable = [
        'type',
    ];

    public function getSearchable()
    {
        return $this->searchable;
    }

    public function getTable()
    {
        return config('laravel-crm.db_table_prefix').'linkedin_templates';
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'user_created_id');
    }

    public function updatedByUser()
    {
        return $this->belongsTo(User::class, 'user_updated_id');
    }

    /**
     * Render template placeholders such as {{first_name}}, {{company}}, {{personalized_opener}}.
     *
     * @param  array<string, string|null>  $variables
     */
    public function render(array $variables = []): string
    {
        $replacements = [];

        foreach ($variables as $key => $value) {
            $replacements['{{'.$key.'}}'] = (string) ($value ?? '');
        }

        return strtr((string) $this->body, $replacements);
    }
}
