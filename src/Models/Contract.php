<?php

namespace VentureDrake\LaravelCrm\Models;

use App\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use VentureDrake\LaravelCrm\Support\Money;
use VentureDrake\LaravelCrm\Traits\BelongsToTeams;
use VentureDrake\LaravelCrm\Traits\HasGlobalSettings;

class Contract extends Model
{
    use BelongsToTeams;
    use HasGlobalSettings;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'sla_penalty_clause' => 'boolean',
        'is_referenceable' => 'boolean',
        'is_design_partner' => 'boolean',
        'kickoff_at' => 'datetime',
        'signed_at' => 'datetime',
        'renewed_at' => 'datetime',
        'partner_rev_share_percent' => 'float',
        'annual_price_escalation_percent' => 'float',
        'bespoke_work_ratio' => 'float',
    ];

    public function getTable()
    {
        return config('laravel-crm.db_table_prefix').'contracts';
    }

    public function setMinimumCommitmentAmountAttribute($value)
    {
        $this->attributes['minimum_commitment_amount'] = Money::toInteger($value);
    }

    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
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
