<?php

namespace VentureDrake\LaravelCrm\Models;

use App\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use VentureDrake\LaravelCrm\Traits\BelongsToTeams;
use VentureDrake\LaravelCrm\Traits\HasCrmActivities;
use VentureDrake\LaravelCrm\Traits\HasCrmFields;
use VentureDrake\LaravelCrm\Traits\HasGlobalSettings;
use VentureDrake\LaravelCrm\Traits\SearchFilters;

class Task extends Model
{
    use BelongsToTeams;
    use HasCrmActivities;
    use HasCrmFields;
    use HasGlobalSettings;
    use SearchFilters;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'start_at' => 'datetime',
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $searchable = [
        'name',
        'description',
    ];

    public function getSearchable()
    {
        return $this->searchable;
    }

    public function getTable()
    {
        return config('laravel-crm.db_table_prefix').'tasks';
    }

    /**
     * Get all of the owning taskable models.
     */
    public function taskable()
    {
        return $this->morphTo('taskable');
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'user_created_id');
    }

    public function updatedByUser()
    {
        return $this->belongsTo(User::class, 'user_updated_id');
    }

    public function deletedByUser()
    {
        return $this->belongsTo(User::class, 'user_deleted_id');
    }

    public function restoredByUser()
    {
        return $this->belongsTo(User::class, 'user_restored_id');
    }

    public function ownerUser()
    {
        return $this->belongsTo(User::class, 'user_owner_id');
    }

    public function assignedToUser()
    {
        return $this->belongsTo(User::class, 'user_assigned_id');
    }

    public function activity()
    {
        return $this->morphOne(Activity::class, 'recordable');
    }

    /**
     * Get the associated Lead model if the task is linked to a lead or a model having a lead.
     */
    public function getLeadAttribute(): ?Lead
    {
        if ($this->taskable instanceof Lead) {
            return $this->taskable;
        }

        if ($this->taskable instanceof Deal && $this->taskable->lead) {
            return $this->taskable->lead;
        }

        if ($this->taskable instanceof Person) {
            return $this->taskable->leads()->latest()->first();
        }

        return null;
    }

    /**
     * Get the resolved LinkedIn URL for the task's linked lead/contact.
     */
    public function getLinkedinUrlAttribute(): ?string
    {
        $raw = null;

        if ($this->taskable instanceof Lead) {
            $raw = $this->taskable->linkedin ?: $this->taskable->person?->linkedin;
        } elseif ($this->taskable instanceof Person) {
            $raw = $this->taskable->linkedin;
        } elseif ($this->taskable instanceof Deal) {
            $raw = $this->taskable->lead?->linkedin ?: $this->taskable->person?->linkedin;
        }

        if (! $raw) {
            return null;
        }

        return str_starts_with($raw, 'http') ? $raw : 'https://'.$raw;
    }

    /**
     * Check if the task's linked lead or account is currently on standby in the relay queue.
     * Note: Completed tasks are never considered standby ("unless completed").
     */
    public function isStandby(): bool
    {
        if ($this->completed_at !== null) {
            return false;
        }

        if (array_key_exists('lead_relay_status', $this->attributes)) {
            return $this->attributes['lead_relay_status'] === 'standby';
        }

        $lead = $this->lead;
        if ($lead) {
            return $lead->relay_status === 'standby' || ($lead->organization && $lead->organization->isDisqualified());
        }

        if ($this->taskable instanceof Organization) {
            return $this->taskable->isDisqualified();
        }

        return false;
    }

    /**
     * Virtual accessor for standby status.
     */
    public function getIsStandbyAttribute(): bool
    {
        return $this->isStandby();
    }

    /**
     * Get the relay status of the task's associated lead.
     */
    public function getRelayStatusAttribute(): ?string
    {
        if (array_key_exists('lead_relay_status', $this->attributes)) {
            return $this->attributes['lead_relay_status'];
        }

        return $this->lead?->relay_status;
    }

    /**
     * Calculate the original planned duration of the task in days.
     */
    public function getOriginalSpanDaysAttribute(): ?int
    {
        if ($this->start_at && $this->due_at) {
            $diff = $this->start_at->diffInDays($this->due_at);

            return max(1, (int) round($diff));
        }

        if ($this->created_at && $this->due_at) {
            $diff = $this->created_at->diffInDays($this->due_at);

            return max(1, (int) round($diff));
        }

        return null;
    }

    /**
     * Rebase this task's deadline starting from now/today and moving forward by $days based on intensity.
     */
    public function rebaseDeadline(int $days = 1): self
    {
        $now = now();
        $newStart = $now->copy();
        $newDue = $now->copy()->addDays($days);

        if ($this->due_at) {
            $newDue->setTime($this->due_at->hour, $this->due_at->minute, $this->due_at->second);
            if ($newDue->lte($newStart)) {
                $newDue = $now->copy()->addDays($days);
            }
        }

        $this->update([
            'start_at' => $newStart,
            'due_at' => $newDue,
        ]);

        return $this;
    }

    /**
     * Scope query to active outreach (excluding uncompleted tasks on standby leads).
     */
    public function scopeActiveOutreach($query)
    {
        $prefix = config('laravel-crm.db_table_prefix', 'crm_');

        return $query->where(function ($q) use ($prefix) {
            $q->whereNotNull($prefix.'tasks.completed_at')
                ->orWhereDoesntHaveMorph('taskable', [Lead::class], function ($leadQuery) {
                    $leadQuery->where('relay_status', 'standby');
                });
        });
    }
}
