<?php

namespace VentureDrake\LaravelCrm\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use VentureDrake\LaravelCrm\Traits\BelongsToTeams;

class Activity extends Model
{
    use BelongsToTeams;
    use SoftDeletes;

    protected $guarded = ['id'];

    public function getTable()
    {
        return config('laravel-crm.db_table_prefix').'activities';
    }

    public function causeable()
    {
        if (empty($this->causeable_type)) {
            if (! empty($this->causeable_id)) {
                $userModel = config('auth.providers.users.model') ?? (class_exists('VentureDrake\LaravelCrm\Tests\Stubs\User') ? 'VentureDrake\LaravelCrm\Tests\Stubs\User' : 'App\Models\User');
                if (class_exists($userModel)) {
                    $this->causeable_type = (new $userModel)->getMorphClass();
                } else {
                    $this->causeable_id = null;
                }
            }
        }

        return $this->morphTo();
    }

    public function timelineable()
    {
        if (empty($this->timelineable_type) && ! empty($this->timelineable_id)) {
            $this->timelineable_id = null;
        }

        return $this->morphTo();
    }

    public function recordable()
    {
        if (empty($this->recordable_type) && ! empty($this->recordable_id)) {
            $this->recordable_id = null;
        }

        return $this->morphTo();
    }
}
